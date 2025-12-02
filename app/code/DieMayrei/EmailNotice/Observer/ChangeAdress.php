<?php


namespace DieMayrei\EmailNotice\Observer;

use DieMayrei\CustomMenu\Helper\Data;
use DieMayrei\EmailNotice\Helper\EmailNotice;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ChangeAdress implements ObserverInterface
{
  /**
   * @var TransportBuilder
   */
    protected $transportBuilder;
  /**
   * @var StoreManagerInterface
   */
    protected $storeManager;
  /**
   * @var LoggerInterface
   */
    protected $logger;

    protected $customerRepositoryInterface;

    protected $appState;

  /**
   * @var Http
   */
    private $request;
  /**
   * @var EmailNotice
   */
    private $config;
  /**
   * @var AddressFactory
   */
    private $addressfactory;

  /**
   * @param TransportBuilder $transportBuilder
   * @param StoreManagerInterface $storeManager
   * @param LoggerInterface $logger
   */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepositoryInterface,
        LoggerInterface $logger,
        Http $request,
        EmailNotice $config,
        AddressFactory $addressFactory,
        State $appState
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->logger = $logger;
        $this->request = $request;
        $this->config = $config;
        $this->addressfactory = $addressFactory;
        $this->appState = $appState;
    }

  /**
   * @param Observer $observer
   * @return $this|void
   * @throws LocalizedException
   * @throws MailException
   * @throws NoSuchEntityException
   */
    public function execute(Observer $observer)
    {
        $moduleName = $this->request->getModuleName();
        $controller = $this->request->getControllerName();
        $action = $this->request->getActionName();
        $route = $this->request->getRouteName();
        if (($moduleName !== 'customer') &&
        ($controller !== 'address') &&
        ($action !== 'formPost') &&
        ($route !== 'customer')
        ) {
            return $this;
        }

        $customerIdent = '';

      /** @var Address $address */
        $address = $observer->getCustomerAddress();
        $address->hasDataChanges();

        $customer = $this->customerRepositoryInterface->getById($address->getCustomerId());
        $defaultBillingAddressId = $customer->getDefaultBilling();
        $defaultShippingAddresId = $customer->getDefaultShipping();

        if ($address->getId() != $defaultBillingAddressId && $address->getId() != $defaultShippingAddresId) {
            return $this;
        }

        $addrById = [];
        $org_addresses = $customer->getAddresses();
        $org_info_adress = $this->addressfactory->create();
      /** @var Magento\Customer\Model\Data\Address $address */
        foreach ($org_addresses as $org_address) {
            $addrById[$org_address->getId()] = $org_address;
            if ($address->getId() == $org_address->getId()) {
                $org_info_adress = $org_address;
            }
        }

        if (!$address) {
            return $this;
        }

        if ($address->getPrefix() == $org_info_adress->getPrefix()
        && $address->getSuffix() == $org_info_adress->getSuffix()
        && $address->getFirstname() === $org_info_adress->getFirstname()
        && $address->getLastname() === $org_info_adress->getLastname()
        && $address->getStreet()[0] === $org_info_adress->getStreet()[0]
        && $address->getStreet()[1] === $org_info_adress->getStreet()[1]
        && $address->getPostcode() === $org_info_adress->getPostcode()
        && $address->getCity() === $org_info_adress->getCity()
        && $address->getCountryId() === $org_info_adress->getCountryId()
        && $address->getTelephone() == $org_info_adress->getTelephone()
        ) {
            return $this;
        }

        $addrCD = strtotime($address->getCreatedAt());
        $addrAge = time() - $addrCD;
        if ($addrAge < 100) {
            return $this;
        }

        if ($cover = $this->customerRepositoryInterface->getById($address->getCustomerId())->getCustomAttribute('cover_id')) {
            $coverid = $cover->getValue();
        } else {
            $coverid = $address->getFirstname() . ' ' . $address->getLastname();
        }
        if ($this->customerRepositoryInterface->getById($address->getCustomerId())->getDefaultShipping() == $address->getId()) {
            $addresstype = 'Versandadresse';
        } elseif ($this->customerRepositoryInterface->getById($address->getCustomerId())->getDefaultBilling() == $address->getId()) {
            $addresstype = 'Rechnungsadresse';
        } else {
            $addresstype = 'Zusätzliche Adresse';
        }

        if ($addresstype !== "Rechnungsadresse") {
            $billingAddr = $addrById[$this->customerRepositoryInterface->getById($address->getCustomerId())->getDefaultBilling() ?? 0] ?? null;
            if ($billingAddr) {
                $customerIdent .= PHP_EOL.'Rechnungsaddr:';
                $customerIdent .= $billingAddr->getFirstname() . ' '. $billingAddr->getLastname() .' -- '.$billingAddr->getPostcode().' '.$billingAddr->getCity(). ', '.implode(" ", $billingAddr->getStreet());
            }
        }


      /* Receiver Detail */
        if ($this->config->getConfig('diemayrei/emailnotice/adresschange_first')) {
            $receivers[] = $this->config->getConfig('diemayrei/emailnotice/adresschange_first');
        }
        if ($this->config->getConfig('diemayrei/emailnotice/adresschange_second')) {
            $receivers[] = $this->config->getConfig('diemayrei/emailnotice/adresschange_second');
        }
        if ($this->config->getConfig('diemayrei/emailnotice/adresschange_third')) {
            $receivers[] = $this->config->getConfig('diemayrei/emailnotice/adresschange_third');
        }
        if ($this->config->getConfig('diemayrei/emailnotice/adresschange_fourth')) {
            $receivers[] = $this->config->getConfig('diemayrei/emailnotice/adresschange_fourth');
        }

        $store = $this->storeManager->getStore();

        $requestData = [];

        $requestData['Adresstyp'] = $addresstype;

        if ($cover = $this->customerRepositoryInterface->getById($address->getCustomerId())->getCustomAttribute('cover_id')) {
            $requestData['Kundennummer '] = $cover->getValue();
        }

        if ($address->getPrefix()) {
            $requestData['Anrede'] = $address->getPrefix();
        }

        if ($address->getSuffix()) {
            $requestData['Titel'] = $address->getSuffix();
        }

        if ($address->getFirstname()) {


            if ($address->getLastname()) {
                $requestData['Nachname'] = $address->getLastname();
                $requestData['Vorname'] = $address->getFirstname();
            }
        }

        if ($address->getStreet()[0]) {
            $requestData['Strasse'] = $address->getStreet()[0];
        }

        if ($address->getStreet()[1]) {
            $requestData['Hausnr'] = $address->getStreet()[1];
        }

        if ($address->getPostcode()) {
            $requestData['PLZ'] = $address->getPostcode();
        }

        if ($address->getCity()) {
            $requestData['Ort'] = $address->getCity();
        }

        if ($address->getCountryId()) {
            $requestData['Land'] = $address->getCountryId();
        }

        if ($address->getTelephone()) {
            $requestData['Telefon'] = $address->getTelephone();
        }

        if ($email = $this->customerRepositoryInterface->getById($address->getCustomerId())->getEmail()) {
            $requestData['E-Mail'] = $email;
        }

        if ($address->getCompany()) {
            $requestData['Firmenname'] = $address->getCompany();
        }
        $orgAdress = [
        'prefix' => $org_info_adress->getPrefix(),
        'suffix' => $org_info_adress->getSuffix(),
        'firstname' => $org_info_adress->getFirstname(),
        'lastname' => $org_info_adress->getLastname(),
        'company' => $org_info_adress->getCompany(),
        'street' => $org_info_adress->getStreet(),
        'plz' => $org_info_adress->getPostcode(),
        'city' => $org_info_adress->getCity(),
        'country' => $org_info_adress->getCountryId(),
        'telephone' => $org_info_adress->getTelephone(),
        ];
        $templateParams = [
        'store' => $store,
        'newAddress' => $requestData,
        'coverid' => $coverid,
        'customerIdent' => $customerIdent,
        'addresstype' => $addresstype,
        'orgiginal_address' => $orgAdress,
        'administrator_name' => 'Kundenservice',
        ];
      /** @var TransportBuilder $transporter */
        $transporter = $this->transportBuilder;
        $transporter->setTemplateIdentifier('chach_notice_email_customer_logged_in_email_template');
        $transporter->setTemplateOptions(['area' => 'frontend', 'store' => $store->getId()]);
        $transporter->setTemplateVars($templateParams);
        $transporter->setFrom('general');
        try {
        //  var_dump($receivers);
            foreach ($receivers as $receiver) {
                $transporter->addTo($receiver);
            }

            $transporter->getTransport()->sendMessage();
        } catch (Exception $e) {

          //  echo $e->getMessage(),PHP_EOL,$e->getTraceAsString(),PHP_EOL;
          //  echo $e->getPrevious() ? $e->getPrevious()->getMessage() : 'nix prev';
          // Write a log message whenever get errors
            $this->logger->critical($e->getMessage());
            $this->logger->critical($e->getTraceAsString());
          // exit;
        }
        return $this;
    }
}
