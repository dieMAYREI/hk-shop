<?php


namespace DieMayrei\EmailNotice\Cron;

use DieMayrei\EmailNotice\Traits\FormatEmailVars;
use DieMayrei\Order2Cover\Model\ExportOrders;
use DieMayrei\Order2Cover\Model\ExportOrdersFactory;
use Exception;
use Magento\Framework\Api\AttributeValue;
use \Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use DieMayrei\EmailNotice\Helper\EmailNotice;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Item\Interceptor;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Psr\Log\LoggerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;

class OrderSuccessMail
{
    use FormatEmailVars;

    /**
     * @var ObjectManager ObjectManager
     */
    protected $objectManager;

    /** @var ExportOrdersFactory  */
    protected $_exportOrdersFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderModel;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var Magento\Framework\Pricing\Helper\Data $pricing
     */
    protected $pricing;

    /** @var \GuzzleHttp\Client  */
    protected $guzzle;

    /**
     * @var State
     */
    protected $appState;

    /**
     * @var Attribute
     */
    protected $attribute;
    /**
     * @var Repository
     */
    protected $attributeRepository;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Payment Helper Data
     *
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentHelper;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;
    /**
     * @var EmailNotice
     */
    protected $config;

    /**
     * @param OrderRepositoryInterface $orderModel
     * @param OrderSender $orderSender
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        OrderRepositoryInterface $orderModel,
        OrderSender $orderSender,
        Data  $pricing,
        TransportBuilder $transportBuilder,
        EmailNotice $config,
        \GuzzleHttp\Client $guzzleClient,
        State $appState,
        ExportOrdersFactory $exportOrdersFactory,
        \Magento\Payment\Helper\Data $paymentHelper,
        Attribute $attribute,
        Repository $attributeRepository,
        LoggerInterface $logger
    ) {
        $this->orderModel = $orderModel;
        $this->orderSender = $orderSender;
        $this->pricing = $pricing;
        $this->objectManager = ObjectManager::getInstance();
        $this->transportBuilder = $transportBuilder;
        $this->config = $config;
        $this->guzzle = $guzzleClient;
        $this->appState = $appState;
        $this->_exportOrdersFactory = $exportOrdersFactory;
        $this->_paymentHelper = $paymentHelper;
        $this->attribute = $attribute;
        $this->attributeRepository = $attributeRepository;
        $this->logger = $logger;
    }

    /**
     * Execute cron to send customer service emails per order.
     *
     * @throws LocalizedException
     * @throws MailException
     */
    public function execute()
    {
        $mutex = new \DieMayrei\Order2Cover\Helper\MyMutex(__FILE__);

        return $mutex->synchronized(function () {

            if ($this->appState->getMode() == \Magento\Framework\App\State::MODE_PRODUCTION) {
                $orderIds = $this->getOrders();

                foreach ($orderIds as $orderId) {
                    try {
                        $this->sendCustomerServiceMail($orderId['entity_id']);
                    } catch (Exception $e) {
                        $this->logger->critical($e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * Send customer service email for a given order ID.
     *
     * @param string $orderId
     * @return $this
     * @throws LocalizedException
     * @throws MailException
     */
    public function sendCustomerServiceMail(string $orderId)
    {
        if (strpos($orderId, '9000') === 0 && strlen($orderId) >= 8) {
            $orderId = substr($orderId, 4);
        }
        /* TODO: Do this with the order repository */
        $order = $this->orderModel->get($orderId);
        /** @var Address $billingAddress */
        $billingAddress = $order->getBillingAddress();
        $billingStreet = $billingAddress->getStreetLine(1) . ' ' . $billingAddress->getStreetLine(2);
        if ($billingAddress instanceof Address) {
            $billingCountry = $this->objectManager->create(\Magento\Directory\Model\Country::class)->load($billingAddress->getCountryId())->getName();
        } else {
            $billingCountry = '';
        }

        /** @var Address $shippingAddress */
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress instanceof \Magento\Sales\Api\Data\OrderAddressInterface) {
            $shippingCountry = $this->objectManager->create(\Magento\Directory\Model\Country::class)->load($shippingAddress->getCountryId())->getName();
            $shippingStreet = $shippingAddress->getStreetLine(1) . ' ' . $shippingAddress->getStreetLine(2);
        } else {
            $shippingCountry = '';
            $shippingStreet = [];
        }

        /** @var  $items */
        $items = $order->getItems();
        $mail = [
            'template' => 'email_notice_kundenservice_order_success',
            'subject' => [],
            'items' => [],
            'order_id' => $order->getId(),
            'kndr' => $order->getCustomerId(),
            'billingAddress' => $billingAddress,
            'billingStreet' => $billingStreet,
            'shippingAddress' => $shippingAddress,
            'shippingStreet' => $shippingStreet,
            'billingCountry' => $billingCountry,
            'shippingCountry' => $shippingCountry,
            'order' => $order,
            'payment' => $this->_paymentHelper->getInfoBlockHtml($order->getPayment(), $order->getStoreId()),
            'lang' => strtoupper($order->getStore()->getCode()),
            'customBillingAddress' => $this->getMyCustomBillingAddress($billingAddress),
            'customShippingAddress' => $shippingAddress instanceof \Magento\Sales\Api\Data\OrderAddressInterface
                ? $this->getMyCustomBillingAddress($shippingAddress, 'shipping')
                : $this->getMyCustomBillingAddress($billingAddress, 'shipping'),
        ];
        $counter = 0;

        /** @var Interceptor $item */
        foreach ($items as $item) {

            if (!$this->isItemInItems($items, $item)) {
                continue;
            }

            /** @var \Magento\Catalog\Model\Product\Interceptor $item */
            $product = $this->objectManager->create(\Magento\Catalog\Model\Product::class)->load($item->getProductId());

            /**
             * Exit if product not found or Prämie
             */
            if (!$product->getId() || $product->getAttributeSetId() == 9) {
                continue;
            }

            $variante = $product->getAttributeText('abovarianten');

            if (!$variante) {
                $variante = $product->getName();
            }

            $mail['subject'][] = $variante;
            $mail['items'][$counter] = $this->getItemInfo($item, $product, $items, $order->getOrderCurrencyCode(), $order->getStore()->getId());

            $counter++;
        }

        if ($counter > 0) {
            $mail['subject'] = implode(', ', $mail['subject']);
            $this->sendMail($mail);

            /** Set the customer_service_notice to 1 */
            $order->setData('customer_service_notice', 1);
            $this->orderModel->save($order);
        }

        return $this;;
    }

    /**
     * Extract gift shipping address data from bundle item options.
     *
     * @param Interceptor $item
     * @return array
     */
    public function setShippingAddress($item)
    {
        $item_options = $item->getProductOptions();
        $gift_address = [];
        if (array_key_exists('options', $item_options)) {
            foreach ($item_options['options'] as $item_option) {
                switch ($item_option['label']) {
                    case 'Anrede':
                        $gift_address['prefix'] = $item_option['value'];
                        break;
                    case 'Vorname':
                        $gift_address['firstname'] = $item_option['value'];
                        break;
                    case 'Nachname':
                        $gift_address['lastname'] = $item_option['value'];
                        break;
                    case 'Straße':
                        $gift_address['street'] = $item_option['value'];
                        break;
                    case 'Hausnr.':
                        $gift_address['housenumber'] = $item_option['value'];
                        break;
                    case 'Postleitzahl':
                        $gift_address['postcode'] = $item_option['value'];
                        break;
                    case 'Ort':
                        $gift_address['city'] = $item_option['value'];
                        break;
                    case 'Land':
                        $gift_address['country_code'] = $item_option['value'];
                        break;
                }
            }
        }
        return $gift_address;
    }

    /**
     * Helper to simulate sending for a single order.
     *
     * @param string $orderId
     * @throws LocalizedException
     * @throws MailException
     */
    public function simulateexecute($orderId)
    {
        $this->sendCustomerServiceMail($orderId);
    }

    /**
     * Build item info array used in email templates.
     *
     * @param Interceptor $item
     * @param \Magento\Catalog\Model\Product\Interceptor $product
     * @param array $items
     * @param string $currency_code
     * @param int $storeid
     * @return array
     */
    protected function getItemInfo($item, $product, &$items, $currency_code, $storeid)
    {
        $variante = '';
        if (substr_count($product->getAttributeText('abovarianten'), 'Leser werben Leser')) {
            $variante = 'Die Prämie erhält:';
        } elseif (substr_count($product->getAttributeText('abovarianten'), 'Geschenk')) {
            $variante = 'Das Geschenkabo erhält:';
        }
        $mail_items['sku'] = $product->getSku();
        $mail_items['variante'] = $variante;
        $mail_items['name'] = $product->getName();
        $mail_items['currency_code'] = $currency_code;
        $mail_items['price_total_inkl_tax'] = $this->pricing->currencyByStore(($item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount() + $item->getDiscountTaxCompensationAmount()), $storeid, true, false);
        $mail_items['qty'] = $item->getQtyToShip();
        $mail_items['aktionskennzeichen'] = $product->getAktionskennzeichen();
        $mail_items['unteraktion'] = $product->getUnteraktion();
        $mail_items['laufende_nummer'] = $product->getLaufendeNummer();
        $mail_items['objekt'] = $product->getAttributeText('objekt');

        if (is_array($item->getProductOptionByCode('bundle_options')) && !empty($item->getProductOptionByCode('bundle_options'))) {
            $option = current($item->getProductOptionByCode('bundle_options'));
            /** Prämie von den Items entfernen  und benötigte Infos holen*/
            /** @var Interceptor $praemie */
            $praemie = $this->removeBonus($items, $option['option_id']);
            $praemie_name = $praemie->getProductOptionByCode('simple_name');
            $praemie_attribute = $praemie->getProductOptionByCode('attributes_info');
            $mail_items['praemienoption']['name'] = $option['value'][0]['title'];
            if ($praemie_name != '') {
                $mail_items['praemienoption']['name'] = $praemie_name;
            }
            if (is_array($praemie_attribute)) {
                foreach ($praemie_attribute as $value) {
                    $mail_items['praemienoption']['attribute'][$value['label']] = $value['value'];
                }
            }
            $mail_items['praemienoption']['sku'] = $praemie->getSku();
        }

        /*
         * Try to find the agrarheute suplements
         */
        if (is_array($item->getProductOptions('options'))) {
            foreach ($item->getProductOptions('options') as $options) {
                if (is_array($options)) {
                    foreach ($options as $option) {
                        if (is_array($option)) {
                            if (array_key_exists('label', $option) &&
                                array_key_exists('value', $option) &&
                                array_key_exists('print_value', $option)
                            ) {
                                $mail_items['suplements'][$option['label']]['label'] = $option['label'];
                                $mail_items['suplements'][$option['label']]['print_value'] = $option['print_value'];
                                $mail_items['suplements'][$option['label']]['value'] = $option['value'];
                            }
                        }
                    }
                }
            }
        }
        return $mail_items;
    }

    /**
     * @param $items
     * @param $option_id
     * @return mixed
     */
    protected function removeBonus(&$items, $option_id)
    {

        foreach ($items as $key => $item) {
            $bundleSelectionAttributes = $item->getProductOptionByCode('bundle_selection_attributes');
            if ($bundleSelectionAttributes) {
                $bundleSelectionAttributesArray = json_decode($bundleSelectionAttributes, true);
                if ($option_id == $bundleSelectionAttributesArray['option_id']) {
                    $praemie = $items[$key];
                    unset($items[$key]);
                    return $praemie;
                }
            }
        }
    }

    /**
     * @param $objekt
     * @param $mailContent
     * @throws LocalizedException
     * @throws MailException
     */
    protected function sendMail($objekt, $mailContent, $replyto = 'aboshop_no_reply@dlv.de')
    {

        $sender = [
            'name' => 'DLV-Shop',
            'email' => 'bestellbestaetigung.dlv-shop@dlv.de',
        ];

        /** @var \Magento\Framework\Mail\Template\TransportBuilder\Interceptor $myMailTransporter */
        $myMailTransporter = $this->transportBuilder;
        $myMailTransporter->setTemplateIdentifier($mailContent['template']);
        $myMailTransporter->setTemplateOptions(['area' => 'frontend', 'store' => 0]);
        /* Receiver Detail */
        if (strpos($objekt, 'SoPro') === false && !$mailContent['dropShipping']) {
            if ($this->config->getConfig('diemayrei/emailnotice/ordersuccess_first')) {
                $myMailTransporter->addTo($this->config->getConfig('diemayrei/emailnotice/ordersuccess_first'));
            }
            if ($objekt) {
                $myMailTransporter->addCc($this->getObjektRecipient($objekt));
            }
        } else {
            if ($mailContent['dropShipping']) {
                if ($mailContent['objekt']) {
                    $myMailTransporter->addTo($mailContent['objekt']);
                } else {
                    $myMailTransporter->addTo(['produkt@dlv.de', 'jennifer.taylor@dlv.de']);
                    $this->logger->error('Keine E-Mail Adresse für Dropshipping gefunden. Bestellung: ' . $mailContent['order_id']);
                }
            } else {
                $myMailTransporter->addTo($this->getObjektRecipient($objekt));
            }
        }
        if ($this->config->getConfig('diemayrei/emailnotice/ordersuccess_second')) {
            $myMailTransporter->addCc($this->config->getConfig('diemayrei/emailnotice/ordersuccess_second'));
        }
        if ($this->config->getConfig('diemayrei/emailnotice/ordersuccess_third')) {
            $myMailTransporter->addCc($this->config->getConfig('diemayrei/emailnotice/ordersuccess_third'));
        }
        if ($this->config->getConfig('diemayrei/emailnotice/ordersuccess_fourth')) {
            $myMailTransporter->addCc($this->config->getConfig('diemayrei/emailnotice/ordersuccess_fourth'));
        }
        $myMailTransporter->setTemplateVars($mailContent);
        $myMailTransporter->setFrom($sender);
        $myMailTransporter->setReplyTo($replyto);

        try {
            $myMailTransporter->getTransport()->sendMessage();
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * @param $items
     * @param Interceptor $item
     * @return bool
     */
    protected function isItemInItems($items, Interceptor $item)
    {
        foreach ($items as $itemInItems) {
            if ($item->getItemId() == $itemInItems->getItemId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $objekt
     * @return mixed|string
     */
    public function getObjektRecipient($objekt)
    {
        $recipientMail = '';

        $recipients = [
            'AFZ' => 'leserservice.afz-derwald@dlv.de',
            'AH' => 'leserservice.agrarheute@dlv.de',
            'ALM' => 'leserservice.almbauer@dlv.de',
            'AT' => 'leserservice.agrartechnik@dlv.de',
            'BIE' => 'leserservice.bienen@dlv.de',
            'BLW' => 'leserservice.wochenblatt@dlv.de',
            'BP' => 'leserservice.bayernspferde@dlv.de',
            'BV' => 'leserservice.braunvieh@dlv.de',
            'BZ' => 'leserservice.bauernzeitung@dlv.de',
            'DBJ' => 'leserservice.dbj@dlv.de',
            'DW' => 'leserservice.waldbesitzer@dlv.de',
            'FF' => 'leserservice@food-and-farm.com',
            'FUT' => 'leserservice.forstundtechnik@dlv.de',
            'FV' => 'leserservice.fleckvieh@dlv.de',
            'GEM' => 'leserservice.gemuese@dlv.de',
            'HI' => 'leserservice.holstein@dlv.de',
            'JGH' => 'leserservice.jagdgebrauchshund@dlv.de',
            'KUR' => 'leserservice.kur@dlv.de',
            'LUF' => 'leserservice.landundforst@dlv.de',
            'LWO' => 'leserservice.wochenblatt@dlv.de',
            'MPF' => 'leserservice.agrarheute@dlv.de',
            'MPM' => 'leserservice.agrarheute@dlv.de',
            'NJ' => 'leserservice.nj@dlv.de',
            'NLR' => 'leserservice.neuelandwirtschaft@dlv.de',
            'OL' => 'leserservice.oldenburger@dlv.de',
            'PI' => 'leserservice.pirsch@dlv.de',
            'SoPro' => 'bestellung-produkt@dlv.de',
            'TRA' => 'leserservice.traction@dlv.de',
            'UJ' => 'leserservice.unserejagd@dlv.de'
        ];

        $recipientMail = $recipients[$objekt] ?? 'jennifer.taylor@dlv.de';

        return $recipientMail;
    }

    /** @return array|ExportOrders[] */
    public function getOrders(): array
    {
        $collection = $this->objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
        $collection->addFieldToSelect(['entity_id']);
        $collection->addFieldToFilter('customer_service_notice', 0);
        $collection->addFieldToFilter('status', ['neq' => 'canceled']);
        $collection->setOrder('entity_id', 'asc');
        return iterator_to_array($collection);
    }
}
