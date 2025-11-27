<?php


namespace DieMayrei\Order2Cover\Observer;

use Carbon\Carbon;
use DieMayrei\DigitalAccess\Observer\DigitalAccess;
use DieMayrei\Order2Cover\Controller\Dev\InTimeSubmit;
use DieMayrei\Order2Cover\Model\ExportOrdersFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Interceptor as ProductInterceptor;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Interceptor as OrderInterceptor;
use Magento\Sales\Model\Order\Item\Interceptor as ItemInterceptor;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Payone\Core\Helper\Database;
use Psr\Log\LoggerInterface;
use libphonenumber\PhoneNumberUtil;

class Order2Cover implements ObserverInterface
{
    /** @var TransportBuilder */
    protected $_transportBuilder;

    /** @var StoreManagerInterface */
    protected $_storeManager;

    /** @var LoggerInterface */
    protected $_logger;

    /** @var CustomerRepositoryInterface */
    protected $_customerRepositoryInterface;

    /** @var Http */
    protected $_request;

    /** @var AddressFactory */
    protected $_addressfactory;

    /** @var CollectionFactory */
    protected $_orderCollectionFactory;

    /** @var ExportOrders */
    protected $_exportOrders;

    /** @var OrderRepositoryInterface */
    protected $_orderRepository;

    /** @var ProductRepositoryInterface */
    protected $_productRepository;

    /** @var AddressInterface  */
    protected $_addressInterface;

    /** @var array */
    protected $_orders4cover;

    /** @var array */
    protected $_tmp_promotions;

    /** @var Rule */
    protected $_saleRule;
    protected $_appState;
    protected $_orderTransmit;
    protected $_payoneDatabaseHelper;
    protected $_orderAddressInterface;


    /**
     * @var RuleRepositoryInterface
     */
    protected $_ruleRepository;

    private const LOG_FILE = BP . '/var/log/order2cover.log';

    protected $configs = [
        'timestamp' => '',
        'transaction_id' => 'The same Unique transaction-ID of registration by calling system',
        'ext_system_type' => 'WEBSHOP',
        'ext_system_id' => '1',
        'ext_system_name' => 'dlv-shop.de',
        'webshop_id' => '1',
        'webshop_name' => 'Deutschland DLV-Shop Deutsch',
        'erp_owner' => 'DLV',
        'erp_system_id' => 'M2-DLVSHOP',
    ];

    public const ATTRIBUTE_SET_ZEITSCHRIFT = 4;
    public const ATTRIBUTE_SET_SONDERPRODUKT = 9;
    public const ATTRIBUTE_SETS = [
        self::ATTRIBUTE_SET_ZEITSCHRIFT => 'Zeitschrift',
        self::ATTRIBUTE_SET_SONDERPRODUKT => 'Sonderprodukt',
    ];

    /**
     * Order2Cover constructor.
     * @param  TransportBuilder  $transportBuilder
     * @param  StoreManagerInterface  $storeManager
     * @param  CustomerRepositoryInterface  $customerRepositoryInterface
     * @param  LoggerInterface  $logger
     * @param  Http  $request
     * @param  AddressFactory  $addressFactory
     * @param  CollectionFactory  $orderCollectionFactory
     * @param  ExportOrdersFactory  $exportOrders
     * @param  OrderRepositoryInterface  $orderRepository
     * @param  ProductRepositoryInterface  $productRepository
     * @param  Database  $payoneDatabaseHelper
     * @param  OrderAddressInterface  $orderAddressInterface
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepositoryInterface,
        LoggerInterface $logger,
        Http $request,
        AddressFactory $addressFactory,
        CollectionFactory $orderCollectionFactory,
        ExportOrdersFactory $exportOrders,
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository,
        Database $payoneDatabaseHelper,
        Rule $saleRule,
        OrderAddressInterface $orderAddressInterface,
        State $appState,
        InTimeSubmit $orderTransmit,
        RuleRepositoryInterface $ruleRepository
    ) {
        $this->_transportBuilder = $transportBuilder;
        $this->_storeManager = $storeManager;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->_logger = $logger;
        $this->_request = $request;
        $this->_addressfactory = $addressFactory;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_exportOrders = $exportOrders;
        $this->_orderRepository = $orderRepository;
        $this->_productRepository = $productRepository;
        $this->_payoneDatabaseHelper = $payoneDatabaseHelper;
        $this->_saleRule = $saleRule;
        $this->_orderAddressInterface = $orderAddressInterface;
        $this->_appState = $appState;
        $this->_orderTransmit = $orderTransmit;
        $this->_ruleRepository = $ruleRepository;
    }

    /**
     * @param  Observer  $observer
     * @return $this|void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $mutex = new \DieMayrei\Order2Cover\Helper\MyMutex(__FILE__);

        $mutex->synchronized(function () {

            $orderids_to_export = $this->getOrdersToExport();

            if (!$orderids_to_export) {
                return $this;
            }


            $loaded_orders = $this->loadOrders($orderids_to_export);

            $errorLogPath = self::LOG_FILE;

            /** @var Order $order */
            foreach ($loaded_orders as $order) {
                try {

                    $this->setConfig('timestamp', date('YmdHis', strtotime($order->getCreatedAt())));

                    if ($this->_appState->getMode() == \Magento\Framework\App\State::MODE_DEVELOPER) {
                        $this->setConfig('transaction_id', '8000' . (string)((int)(microtime(true) * 1000)));
                    } else {
                        $this->setConfig('transaction_id', (string)((int)(microtime(true) * 1000)));
                    }

                    $this->getTransaction($order);
                    $this->getCommon($order);
                    $this->getAddress($order, 'billing');
                    $this->getAddress($order, 'shipping');
                    $this->getOrderpositions($order);
                    $paymentMethod = $this->getPayment($order);

                    if (!in_array($paymentMethod, ['checkmo', 'debitpayment', 'free'])) {
                        if ($order->getStatus() != Order::STATE_PROCESSING && $order->getStatus() != Order::STATE_COMPLETE) {
                            continue;
                        }
                    }

                    $exportedOrder = $this->_exportOrders->create();

                    $exportedOrder->setData([
                        'order_id' => $order->getId(),
                        'payload' => json_encode(['order' => $this->_orders4cover[$order->getId()]]),
                        'created_at' => Carbon::now(),
                    ]);
                    $exportedOrder->save();

                    if ($paymentMethod != "payone_creditcard") {
                        $order->setState(Order::STATE_COMPLETE)->setStatus(Order::STATE_COMPLETE);
                        $order->save();
                    }
                } catch (\Throwable $error) {
                    file_put_contents($errorLogPath, $order->getId() . ': ' . $error->getMessage() . ' : ' . $error->getTraceAsString(), FILE_APPEND);
                }
            }
        });

        switch ($this->_appState->getMode()) {
            case \Magento\Framework\App\State::MODE_DEVELOPER:
                $this->_orderTransmit->execute();
                break;
        }
    }

    protected function getOrdersToExport(): array|bool
    {
        /** @var \DieMayrei\Order2Cover\Model\ExportOrders $exportOrdersModel */
        $exportOrdersModel = $this->_exportOrders->create();

        $connection = $exportOrdersModel->getResource()->getConnection();
        $select = $connection->select()
            ->from($exportOrdersModel->getResource()->getMainTable(), 'order_id')
            ->where('created_at > ?', date('Y-m-d H:i:s', strtotime('-30 day')));
        $exported_orders = $connection->fetchAll($select);

        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->_orderCollectionFactory->create();
        $collection->addFieldToSelect('order_id');
        $collection->addFieldToFilter('created_at', ['gt' => date('Y-m-d H:i:s', strtotime('-3 day'))]);
        $collection->addFieldToFilter('status', ['neq' => 'canceled']);
        $collection->setOrder('created_at', 'desc');
        $order_to_export = $collection->getAllIds();

        $exportedOrderIds = [];
        foreach ($exported_orders as $order) {
            $exportedOrderIds[] = $order['order_id'];
            if (strpos($order['order_id'], '9000') === 0 && strlen($order['order_id']) >= 8) {
                $exportedOrderIds[] = substr($order['order_id'], 4);
            }
        }

        $order_to_export = array_diff($order_to_export, $exportedOrderIds);

        if (count($order_to_export)) {
            return $order_to_export;
        }

        return false;
    }

    /**
     * @param $orderids_to_export
     * @return array
     */
    protected function loadOrders($orderids_to_export)
    {
        $return = [];
        foreach ($orderids_to_export as $order_id) {
            $return[] = $this->_orderRepository->get($order_id);
        }

        return $return;
    }

    /**
     * @param  OrderInterceptor  $order
     */
    protected function getAddress($order, $type)
    {
        /** @var  Address $address */
        if ($type === 'shipping') {
            $address = $order->getShippingAddress();
            if (!$address) {
                return;
            }
        }
        /** @var  Address $address */
        if ($type === 'billing') {
            $address = $order->getBillingAddress();
            if (!$address) {
                return;
            }
        }
        $return = [];
        if ($type === 'billing') {
            $return['timestamp'] = $this->getConfig('timestamp');
            $return['ext_system_id'] = $this->getConfig('ext_system_id');
            //$return['ext_customer_sub_nr'] = $address->getId();
            $return['erp_system_id'] = $this->getConfig('erp_system_id');
            $this->addErpCustomer($return, $order);
        }
        $return['salutation_code'] = $this->getAnrede($this->getSalutationCode($address->getPrefix()));
        $return['title_code'] = $this->getTitleCode($this->getTitle($address->getSuffix()));
        $return['firstname'] = $address->getFirstname();
        $return['lastname'] = $address->getLastname();
        $return['company'] = $address->getCompany();
        $return['country_code'] = $address->getCountryId();
        $return['street'] = $address->getStreetLine(1) . ' ' . $address->getStreetLine(2);
        $return['street2'] = $address->getStreetLine(3);
        $return['postcode'] = $address->getPostcode();
        $return['city'] = $address->getCity();
        $return['vat_number'] = $address->getVatId();
        $return['email'] = $address->getEmail();
        if ($type === 'billing') {
            $return['invoice_email'] = $address->getEmail();
            $return['EMAIL'] = $address->getEmail();
        }
        $return['phone'] = $address->getTelephone();
        if (trim($address->getTelephone() ?: '')) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            try {
                $phoneNumberRaw = $phoneUtil->parse($address->getTelephone(), $address->getCountryId());
                $phoneNumberFormatted = $phoneUtil->format($phoneNumberRaw, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
                $return['phone'] = $phoneNumberFormatted;
            } catch (\Exception $error) {
                file_put_contents(
                    self::LOG_FILE,
                    $order->getId() . ' phoneparseerror: ' . $error->getMessage() . '  ' . $address->getTelephone(),
                    FILE_APPEND
                );
                $return['phone'] = null;
            }
        }


        $this->_orders4cover[$order->getId()]['addresses'][$type] = $return;
    }

    /**
     * @param $return
     * @param $order
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function addErpCustomer(&$return, $order)
    {
        if (!$order->getCustomerId()) {
            return;
        }
        $customer = $this->_customerRepositoryInterface->getById($order->getCustomerId());

        if ($customer->getCustomAttribute('cover_id') && $cover_id = $customer->getCustomAttribute('cover_id')->getValue()) {
            $return['erp_customer_nr'] = substr($cover_id, 0, (strlen($cover_id) - 3));
            $return['erp_customer_sub_nr'] = substr($cover_id, -3);
        }

        if ($customer->getCustomAttribute('mydlv_id') && $mydlv_id = $customer->getCustomAttribute('mydlv_id')->getValue()) {
            $return['ext_customer_nr'] = 'MYDLV_' . $mydlv_id;
            $return['ext_customer_sub_nr'] = '0';
        }
    }

    protected function getPayment($order)
    {

        $method = $order->getPayment()->getMethod();

        $payment = [];
        $payment['transaction_date'] = date('d.m.Y', strtotime($order->getCreatedAt()));
        $payment['currency'] = $order->getOrderCurrency()->toString();

        switch ($method) {
            case 'checkmo':
                $payment['payment_provider'] = 'ERP';
                $payment['payment_type_ext'] = 'F';
                break;
            case 'debitpayment':
                $additional_information = $order->getPayment()->getAdditionalInformation();
                $payment['payment_provider'] = 'ERP';
                $payment['payment_type_ext'] = 'C';
                $payment['iban'] = $additional_information['additional_data']['iban'];
                $payment['bic'] = $additional_information['additional_data']['bic'];
                $payment['account_owner'] = $additional_information['additional_data']['bank_account_owner'];
                $payment['bank_name'] = $additional_information['additional_data']['bank_company'];
                // $payment['sepa_mandate_granted'] = true;
                break;
            case 'payone_paypal':
                $payment['payment_provider'] = 'PAYONE';
                $payment['payment_type_ext'] = 'PAYONE_P';
                $payment['transaction_code'] = $order->getPayment()->getLastTransId();
                $userId = $this->getGuestPayPalUserId($order->getId());
                $payment['user_id'] = $userId;
                break;
            case 'payone_creditcard':
                $payment['payment_provider'] = 'PAYONE';
                $payment['payment_type_ext'] = 'PAYONE_K';
                $payment['transaction_code'] = $order->getPayment()->getLastTransId();
                $userId = $this->getGuestPayPalUserId($order->getId());
                $payment['user_id'] = $userId;
                break;
            case 'payone_obt_sofortueberweisung':
                $payment['payment_provider'] = 'PAYONE';
                $payment['payment_type_ext'] = 'PAYONE_S';
                $payment['transaction_code'] = $order->getPayment()->getLastTransId();
                break;
        }
        $this->_orders4cover[$order->getId()]['payment'] = $payment;

        return $method;
    }

    protected function getGuestPayPalUserId($orderid)
    {
        if ($this->_appState->getMode() == \Magento\Framework\App\State::MODE_DEVELOPER) {
            return 0;
        }

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $om->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('payone_protocol_transactionstatus');

        $sql = $connection->select()
            ->from($tableName, 'userid')
            ->where('order_id = ?', $this->_payoneDatabaseHelper->getIncrementIdByOrderId($orderid));

        $result = $connection->fetchAll($sql);

        if (empty($result)) {
            throw new \Exception('No payone status for paypal tx ' . $orderid);
        }

        return $result[0]['userid'];
    }

    /**
     * @param  OrderInterceptor  $order
     */
    protected function getCommon($order, $isSplitGa = false)
    {
        $this->_orders4cover[$order->getId()]['order_number'] = $order->getRealOrderId();
        $this->_orders4cover[$order->getId()]['order_id_unique'] = $order->getId();
        if ($isSplitGa) {
            $this->_orders4cover[$order->getId()]['order_number'] .= '_1';
            $this->_orders4cover[$order->getId()]['order_id_unique'] .= '_1';
        }

        if ($this->_appState->getMode() == \Magento\Framework\App\State::MODE_DEVELOPER) {
            $this->_orders4cover[$order->getId()]['order_number'] = '8000' . ltrim($this->_orders4cover[$order->getId()]['order_number'], '0');
            $this->_orders4cover[$order->getId()]['order_id_unique'] = '8000' . $this->_orders4cover[$order->getId()]['order_id_unique'];
        }

        $this->_orders4cover[$order->getId()]['bestell_text'] = $order->getRealOrderId();
        $this->_orders4cover[$order->getId()]['order_date'] = date('d.m.Y', strtotime($order->getCreatedAt()));
        $this->_orders4cover[$order->getId()]['total_amount'] = (float)$order->getGrandTotal();
        $this->_orders4cover[$order->getId()]['currency'] = $order->getOrderCurrency()->toString();
        $this->_orders4cover[$order->getId()]['shipping_code_ext'] = 'VERS_KOST';
        $this->_orders4cover[$order->getId()]['shipping_amount'] = (float)$order->getShippingInclTax();
        $this->_orders4cover[$order->getId()]['optin_general'] = $order->getCoverOptinGeneral() ? 'T' : '';
    }

    /**
     * @param  OrderInterceptor  $order
     */
    protected function getTransaction($order)
    {

        $transaction['timestamp'] = $this->getConfig('timestamp');
        $transaction['transaction_id'] = $this->getConfig('transaction_id');
        $transaction['ext_system_type'] = $this->getConfig('ext_system_type');
        $transaction['ext_system_id'] = $this->getConfig('ext_system_id');
        $transaction['ext_system_name'] = $this->getConfig('ext_system_name');
        $transaction['webshop_id'] = $this->getConfig('webshop_id');
        $transaction['webshop_name'] = $this->getConfig('webshop_name');
        $transaction['erp_owner'] = $this->getConfig('erp_owner');
        $transaction['erp_system_id'] = $this->getConfig('erp_system_id');

        $this->_orders4cover[$order->getId()]['transaction'] = $transaction;
    }

    /**
     * @param  OrderInterceptor  $order
     * @throws NoSuchEntityException
     */
    protected function getOrderpositions($order)
    {
        $discountFixed = false;
        if ($order->getAppliedRuleIds()) {
            foreach (explode(',', $order->getAppliedRuleIds()) as $ruleId) {
                $rule = $this->getRuledata($ruleId);
                if ($rule->getSimpleAction() == 'cart_fixed') {
                    $discountFixed = $rule->getDiscountAmount() / $order->getTotalQtyOrdered();
                }
            }
        }
        /** @var ItemInterceptor $item */
        foreach ($order->getAllItems() as $item) {
            /** @var  $product */
            $product = $this->_productRepository->getById($item->getProductId());

            /** @var ProductInterceptor $attributeSetId */
            $attributeSetId = $product->getAttributeSetId();
            switch ($attributeSetId) {
                case 9:
                    $this->_tmp_promotions[$order->getId()][$item->getParentItemId()] = $this->setPromotions(
                        $item,
                        $product
                    );
                    break;
                case 10:
                    /**
                     * If a subscription is a trial issue, a special product must be passed
                     */
                    if ($product->getCustomAttribute('abovarianten')->getValue() == 27) {
                        $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosArticle(
                            $item,
                            $product,
                            $attributeSetId
                        );
                    } else {
                        $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosAbo(
                            $item,
                            $product,
                            $order
                        );
                    }
                    if (substr_count($product->getAttributeText('abovarianten'), 'Leser werben Leser')) {
                        $item_options = $item->getProductOptions();
                        $add_gift_address = false;
                        $gift_address = [];
                        if (array_key_exists('options', $item_options)) {
                            $gift_address['salutation_code'] = '';
                            foreach ($item_options['options'] as $item_option) {
                                switch ($item_option['label']) {
                                    case 'Anrede':
                                        $gift_address['salutation_code'] = $this->getAnrede($item_option['value']);
                                        $add_gift_address = true;
                                        break;
                                    case 'Vorname':
                                        $gift_address['firstname'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Nachname':
                                        $gift_address['lastname'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'E-Mail-Adresse':
                                        $gift_address['email'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Straße':
                                        $gift_address['street'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Hausnr.':
                                        $gift_address['street'] .= ' ' . $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Postleitzahl':
                                        $gift_address['postcode'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Ort':
                                        $gift_address['city'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                    case 'Land':
                                        $gift_address['country_code'] = $item_option['value'];
                                        $add_gift_address = true;
                                        break;
                                }
                            }
                            if ($add_gift_address) {
                                $this->_orders4cover[$order->getId()]['addresses']['gift'] = $gift_address;
                                if (isset($this->_orders4cover[$order->getId()]['addresses']['shipping'])) {
                                    $shippAddr = $this->_orders4cover[$order->getId()]['addresses']['shipping'];
                                    $billAddr = $this->_orders4cover[$order->getId()]['addresses']['billing'];
                                    if ($billAddr['firstname'] == $shippAddr['firstname'] &&
                                        $billAddr['street'] == $shippAddr['street'] &&
                                        $billAddr['street2'] == $shippAddr['street2'] &&
                                        $billAddr['postcode'] == $shippAddr['postcode']
                                    ) {
                                        unset($this->_orders4cover[$order->getId()]['addresses']['shipping']);
                                    }
                                }
                            }
                        }
                    }
                    break;
                // Special products / Book products
                case 11:
                case 12:
                case 13:
                    if ($item->getProductOptions() && ($item->getBuyRequest()->getSelectedConfigurableOption() || $item->getBuyRequest()->getSelectionConfigurableOption())) {
                        if (array_key_exists('attributes_info', $item->getProductOptions())) {
                            if (array_key_exists('simple_sku', $item->getProductOptions())) {
                                if ($item->getBuyRequest()->getSelectedConfigurableOption()) {
                                    $product = $this->_productRepository->getById($item->getBuyRequest()->getSelectedConfigurableOption());
                                } else {
                                    $product = $this->_productRepository->getById($item->getBuyRequest()->getSelectionConfigurableOption()[array_key_first($item->getBuyRequest()->getSelectionConfigurableOption())]);
                                }
                                $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosArticle(
                                    $item,
                                    $product,
                                    $attributeSetId
                                );
                            }
                        }
                    } else {
                        $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosArticle(
                            $item,
                            $product,
                            $attributeSetId
                        );
                    }
                    break;
            }
        }
        // Adding Promotions to OrderPositions
        $this->addPromotions2Positions($order);
    }

    /**
     * @param  OrderInterceptor  $order
     */
    protected function addPromotions2Positions($order)
    {

        if (!array_key_exists('orderpositions', $this->_orders4cover[$order->getId()])) {
            return;
        }
        foreach ($this->_orders4cover[$order->getId()]['orderpositions'] as &$order_position) {
            if (array_key_exists('item_id', $order_position)) {
                if (isset($this->_tmp_promotions[$order->getId()]) && is_array($this->_tmp_promotions[$order->getId()])) {
                    if (array_key_exists($order_position['item_id'], $this->_tmp_promotions[$order->getId()])) {
                        $order_position['promotions'][] = $this->_tmp_promotions[$order->getId()][$order_position['item_id']];
                        unset($order_position['item_id']);
                    }
                }
            }
        }
    }

    /**
     * @param  ItemInterceptor  $item
     * @param  ProductInterceptor  $product
     * @return array
     */
    protected function setPromotions(
        ItemInterceptor $item,
        ProductInterceptor $product
    ) {

        $options = $item->getProductOptions();
        //var_dump($options);
        //$bonus_erp_sku = false;
        $price = 0;
        //if (array_key_exists('simple_sku',$options)){
        //    $bonus_erp_sku = $options['simple_sku'];
        //}

        $bundle_selection_attributes = false;
        if (array_key_exists('bundle_selection_attributes', $options)) {
            $bundle_selection_attributes = $options['bundle_selection_attributes'];
            $bundle_selection_attributes = json_decode($bundle_selection_attributes, true);
            if (array_key_exists('price', $bundle_selection_attributes)) {
                $price = (float)$bundle_selection_attributes['price'];
            }
        }

        $return = [];
        $return['sku'] = str_replace('-Prämie', '', $product->getSku());
        //if ( $bonus_erp_sku){
        //$return['bonus_erp_sku'] = $options['simple_sku'];
        //}
        $return['bonus_erp_sku'] = str_replace('-Prämie', '', $product->getSku());
        $itemSku = str_replace('off-', '', $item->getSku());
        if (strpos($itemSku, '-') !== false) {
            $return['bonus_erp_subsku'] = str_replace($return['bonus_erp_sku'] . '-', '', $itemSku);
        } else {
            // bonus_erp_subsku default value changed from 0 to empty string per Mrs. Nirsch's instructions
            $return['bonus_erp_subsku'] = '';
        }
        $return['add_payment'] = $price;
        return $return;
    }

    /**
     * @param  ItemInterceptor  $item
     * @param  ProductInterceptor  $product
     * @return array
     */
    protected function getOrderPosArticle(
        ItemInterceptor $item,
        ProductInterceptor $product,
        int $attributeSetId
    ) {
        $erpskuSplit = $this->getErpSubsku($product->getSku(), $attributeSetId);

        $orderpostion = [
            'type' => 'B',
            'quantity' => (int)$item->getQtyOrdered(),
            'price' => number_format((float)(($item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount() + $item->getDiscountTaxCompensationAmount()) / (int)$item->getQtyOrdered()), 2),
            'erp_sku' => $erpskuSplit[0],
            'erp_subsku' => $erpskuSplit[1],
        ];

        return $orderpostion;
    }

    /**
     * Determines the value for erp_subsku/edition number
     * according to defined rules
     *
     * @param $sku
     * @param $attributeSetId
     * @return array
     */
    protected function getErpSubsku($sku, $attributeSetId)
    {
        /**
         * Sometimes a product with 'off-' in the SKU is excluded from stock update,
         * in this case we do not perform the subsku search
         */
        if (substr_count($sku, 'off-') > 0) {
            $sku = str_replace('off-', '', $sku);
            return [$sku, 0];
        }

        /* erp_subsku is only changed for book products. */
        if ($attributeSetId == 11 || $attributeSetId == 12) {
            /**
             * The rule is: For determining the edition number,
             * the SKU may contain exactly one '-'
             */
            if (substr_count($sku, '-') == 1) {
                return explode('-', $sku);
            }
        }
        return [$sku, 0];
    }

    /**
     * @param  ItemInterceptor  $item
     * @param  ProductInterceptor  $product
     * @param  OrderInterceptor  $order
     * @return array
     */
    protected function getOrderPosAbo(
        ItemInterceptor $item,
        ProductInterceptor $product,
        $order
    ) {

        $orderposition = [];
        $orderposition['type'] = 'A';

        $quantity = $item->getQtyOrdered();
        if ($quantity) {
            $orderposition['quantity'] = (int)$quantity;
        }
        if ($product->getCustomAttribute('aktionskennzeichen')) {
            $orderposition['campaign_code'] = $product->getCustomAttribute('aktionskennzeichen')->getValue();
        }
        if ($product->getCustomAttribute('unteraktion')) {
            $orderposition['campaign_subcode'] = $product->getCustomAttribute('unteraktion')->getValue();
        }
        if ($product->getCustomAttribute('laufende_nummer')) {
            $orderposition['campaign_offer_number'] = $product->getCustomAttribute('laufende_nummer')->getValue();
        }
        if ($this->getAboType($product->getCustomAttribute('abovarianten')->getValue())) {
            $orderposition['abo_typ'] = $this->getAboType($product->getCustomAttribute('abovarianten')->getValue());
        }
        $orderposition['object_id'] = $this->getObjectId(
            $product->getAttributeText('objekt'),
            $product->getAttributeText('medium'),
            $product,
        );
        $orderposition['edition_id']  = $this->getEdition(
            $order,
            $product,
            $item,
            $product->getAttributeText('medium')
        );

        $orderposition['price'] = (float)$item->getPriceInclTax();
        $orderposition['start_date'] = date('d.m.Y', strtotime($order->getCreatedAt()));
        $orderposition['item_id'] = $item->getId();

        return $orderposition;
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getConfig($key)
    {
        return $this->configs[$key];
    }

    /**
     * @param $key
     * @param $value
     */
    protected function setConfig($key, $value)
    {
        $this->configs[$key] = $value;
    }

    protected function getAboType($abovariante)
    {
        $return = '';

        switch ($abovariante) {
            //Persönliches Abo Print
            case 37:
                // Probeheft
            case 27:
                //Probelesen
            case 21:
                //Schnupper Abo
            case 45:
                //Sonder Abo
            case 11:
                //Studenten Abo
            case 20:
                //EPaper + Print Abo
            case 15:
                //EPaper Upgrade
            case 31:
                //EPaper Abo
            case 16:
                //EPaper Probelesen
            case 18:
                //Einzelprodukt kostenpflichtig
            case 17:
                //Extern
            case 235:
                //Baumpflege Abo
            case 236:
                //aClub 3 Monate testen
            case 237:
                //aClub Studenten Abo
            case 238:
                //Schulungspaket
            case 239:
                //Ausbildungspaket
            case 240:
                // EPaper Trial Reading for Magazine Readers
            case 241:
                //Geschenk Probeheft
            case 242:
                //Persönliches Abo Plus
            case 245:
                //Schnupper Abo Plus
            case 246:
                //Studenten Abo Plus
            case 248:
                //Digitalmagazin
            case 253:
                //Digitalmagazin Upgrade
            case 254:
                //Digitalmagazin Probelesen
            case 255:
                //E-Paper Schnupperabo
            case 256:
                $return = 'EA';
                break;
            //Geschenk Abo
            case 13:
                //Geschenk Abo Plus
            case 249:
                //Geschenk Schnupperabo
            case 250:
                $return = 'GA';
                break;
            //2 Jahre Leser werben Leser
            case 227:
                //Leser werben Leser
            case 14:
                //Leser werben Leser Plus
            case 247:
                $return = 'LWL';
                break;
            case 5679:
                $return = 'DA';
                break;
        }

        return $return;
    }

    /**
     * Converts salutation to Cover API code
     *
     * @param string|null $salutation
     * @return string
     */
    protected function getAnrede($salutation): string
    {
        $mapping = [
            'Herr' => '1',
            'Frau' => '2',
            'Firma' => '3',
            'Familie' => '4',
        ];

        if ($salutation && array_key_exists($salutation, $mapping)) {
            return $mapping[$salutation];
        }

        return '0'; // Default: no salutation
    }

    /**
     * Extracts title from suffix (e.g. "Dr.", "Prof.")
     *
     * @param string|null $suffix
     * @return string|null
     */
    protected function getTitle($suffix): ?string
    {
        if (!$suffix) {
            return null;
        }

        // Remove trailing spaces and periods
        return trim($suffix, ' .');
    }

    /**
     * Converts title to Cover API code
     *
     * @param string|null $title
     * @return string
     */
    protected function getTitleCode($title): string
    {
        $mapping = [
            'Dr' => '10',
            'Prof' => '20',
            'Prof. Dr' => '30',
        ];

        if ($title && array_key_exists($title, $mapping)) {
            return $mapping[$title];
        }

        return '47'; // Default: no title
    }

    /**
     * @param $salutation
     * @return string
     */
    protected function getSalutationCode($salutation)
    {
        $arr = [
            'Herr' => 'Herr',
            'Herrn' => 'Herr'
        ];

        if (array_key_exists($salutation, $arr)) {
            return $arr[$salutation];
        }

        return $salutation;
    }

    /**
     * @param $order
     * @param $product
     * @param $item
     * @return string
     */
    protected function getEdition(Order $order, Product $product, $item, $medium)
    {

        // 16 is the store ID for Austria
        if ($order->getStoreId() == '16') {
            if ($product->getSku() == 'BLW-DigitalmagazinProbelesen-AH23KPUMFPH-141') {
                return '1';
            }
        }

        if ($product->getAttributeText('objekt') === 'AH') {
            if ($product->getMydlvGuid()) {
                $groupId = $product->getMydlvGuid();
            } else {
                $groupId = DigitalAccess::getAhGroupId($item);
            }

            if ($medium === 'Digital' || $medium === 'E-Paper') {
                return (int) str_replace('4f79e6de-7f0c-4756-84ac-a00', '', $groupId);
            } else {
                return (int) str_replace('4f79e6de-7f0c-4756-84ac-a0000000001', '', $groupId);
            }
        }

        $object = $product->getAttributeText('objekt');

        if (in_array($object, ['LWO'])) {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return '1';
            }
            return 'ÖSTERREICH';
        }

        if ($medium == 'E-Paper' || $medium == 'Digital') {
            return '-';
        }

        /**
         * Special case for AFZ Tree Care is implemented here,
         * for AboType 236 the edition 'Baumpflege' is passed
         */
        $aboType = $product->getCustomAttribute('abovarianten')->getValue();
        if ($aboType == 236) {
            return 'Baumpflege';
        }

        /** Special case for Biene&Natur */
        if (in_array($object, ['BIE'])) {
            return '-';
        }

        /** Continue only for BLW and LuF */
        if (in_array($object, ['BLW', 'LUF'])) {
            return 'XXX';
        }

        return '-';
    }

    protected function ifProbeheftSplitRequired($order)
    {
        $probeheft = false;
        $orderdItems = 0;

        $errorLogPath = self::LOG_FILE;

        /** @var ItemInterceptor $item */
        foreach ($order->getAllItems() as $item) {
            try {
                $product = $this->_productRepository->getById($item->getProductId());

                /** @var ProductInterceptor $attributeSetId */
                $attributeSetId = $product->getAttributeSetId();
                if ($attributeSetId == 10) {
                    if ($product->getCustomAttribute('abovarianten')->getValue() == 27) {
                        $probeheft = true;
                    }
                }
                // We only count order positions for special products and subscriptions
                if (in_array($attributeSetId, [10, 11, 12])) {
                    $orderdItems++;
                }
            } catch (\Throwable $error) {
                file_put_contents($errorLogPath, $order->getId() . ': ' . $error->getMessage() . ' : ' . $error->getTraceAsString(), FILE_APPEND);
            }
        }
        if ($orderdItems >= 1 && $probeheft) {
            return true;
        }

        return false;
    }

    /**
     * @param $order
     */
    protected function recalculateTotals(&$order)
    {
        $orderSubTotal = 0;
        $orderBaseTax = 0;
        $orderDiscountAmount = 0;
        foreach ($order->getAllVisibleItems() as $_item) {
            $orderSubTotal += $_item->getBaseRowTotal();
            $orderBaseTax += $_item->getBaseTaxAmount() + $_item->getBaseHiddenTaxAmount();
            $orderDiscountAmount += $_item->getBaseDiscountAmount();
        }

        $grandTotal = ($orderSubTotal + $order->getShippingAmount() + $orderBaseTax) - $orderDiscountAmount;

        # Update Order Totals
        $order->setSubtotal($orderSubTotal)
            ->setBaseSubtotal($orderSubTotal)
            ->setDiscountAmount($orderDiscountAmount)
            ->setBaseDiscountAmount($orderDiscountAmount)
            ->setTaxAmount($orderBaseTax)
            ->setBaseTaxAmount($orderBaseTax)
            ->setGrandTotal($grandTotal)
            ->setBaseGrandTotal($grandTotal);
    }

    /**
     * @param $object
     * @param $medium
     * @return string
     *
     * Values for medium Print|E-Paper|Print &amp; E-Paper|Digital|Print + Digital
     * Values for $object
     * BP 60
     * FV 68
     * GEM 63
     * HI 64
     * JGH 56
     * LUF 57
     * NJ 55
     * NLR 65
     * OL 66
     * TRA TRA
     * UJ 51
     *
     */
    protected function getObjectId($object, $medium, Product $product)
    {

        /// EPH PI-Digitalmagazin-PI25DMHPEPH-13
        if (preg_match('/^PI-Digitalmagazin-PI\d+DMHPEPH-13$/', $product->getSku())) {
            return 'EPH';
        }

        if ($object == 'AFZ') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EAF';
            }
        }
        if ($object == 'AT') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EAT';
            }
        }
        if ($object == 'AH') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EAG';
            }
            return 'AH';
        }
        if ($object == 'BIE') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EBI';
            }
            return 'BIE';
        }
        if ($object == 'BLW') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EB';
            }
        }
        if ($object == 'LWO') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EB';
            }
            return 'BLW';
        }
        if ($object == 'FUT') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EF';
            }
        }
        if ($object == 'FF') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EFF';
            }
        }
        if ($object == 'PI') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EP';
            }
        }
        if ($object == 'TRA') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'ET';
            }
        }
        if ($object == 'UJ') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EU';
            }
        }
        if ($object == 'LUF') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EL';
            }
        }
        if ($object == 'DW') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EDW';
            }
        }
        if ($object == 'NJ') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'ENJ';
            }
        }
        if ($object == 'KUR') {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EK';
            }
        }
        if ($object == "BZ") {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EBZ';
            }
        }
        if ($object == "DBJ") {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EDB';
            }
        }
        if ($object == "GEM") {
            if ($medium == 'E-Paper' || $medium == 'Digital') {
                return 'EGE';
            }
        }

        return $object;
    }

    /**
     * @return RuleInterface|null
     */
    public function getRuledata($ruleId): ?RuleInterface
    {
        $salesRule = null;
        try {
            $salesRule = $this->_ruleRepository->getById($ruleId);
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
        }
        return $salesRule;
    }
}
