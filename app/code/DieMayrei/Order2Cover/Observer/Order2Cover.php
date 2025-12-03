<?php


namespace DieMayrei\Order2Cover\Observer;

use Carbon\Carbon;
use DieMayrei\EmailNotice\Traits\FormatEmailVars;
use DieMayrei\Order2Cover\Controller\Dev\InTimeSubmit;
use DieMayrei\Order2Cover\Model\ExportOrdersFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
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
    use FormatEmailVars;

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
        'ext_system_id' => '40',
        'ext_system_name' => 'shop.hk-verlag.de',
        'webshop_id' => '1',
        'webshop_name' => 'Deutschland HK-Shop Deutsch',
        'erp_owner' => 'DLV',
        'erp_system_id' => 'M2-HKVSHOP',
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

                    if (!in_array($paymentMethod, ['checkmo', 'diemayrei_sepa', 'free'])) {
                        if (in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE])) {
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
                        #$order->save(); //TODO remove after refactoring
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

        $return['salutation_code'] = $this->getAnrede($address->getPrefix());
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
            case 'diemayrei_sepa':
                $additionalInfo = $order->getPayment()->getAdditionalInformation();
                $payment['payment_provider'] = 'ERP';
                $payment['payment_type_ext'] = 'C';
                $payment['iban'] = $additionalInfo['iban'] ?? '';
                $payment['bic'] = $additionalInfo['bic'] ?? '';
                $payment['account_owner'] = $additionalInfo['account_holder'] ?? '';
                $payment['bank_name'] = $additionalInfo['bank_name'] ?? '';
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
                case self::ATTRIBUTE_SET_ZEITSCHRIFT:
                    $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosAbo(
                        $item,
                        $product,
                        $order
                    );
                    break;
                default:
                    // For configurable/bundle products, get the actual simple product
                    $actualProduct = $this->getSimpleProductFromItem($item, $product);
                    $this->_orders4cover[$order->getId()]['orderpositions'][] = $this->getOrderPosArticle(
                        $item,
                        $actualProduct,
                        $attributeSetId
                    );
                    break;
            }
        }
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

        if ($product->getCustomAttribute('laufende_nummer')) {
            $orderposition['campaign_offer_number'] = $product->getCustomAttribute('laufende_nummer')->getValue();
        }

        $orderposition['object_id'] = $this->getObjectCodeFromSku($product->getSku());

        $orderposition['edition_id']  = '-';

        $orderposition['price'] = (float)$item->getPriceInclTax();
        $orderposition['start_date'] = date('d.m.Y', strtotime($order->getCreatedAt()));
        $orderposition['item_id'] = $item->getId();

        return $orderposition;
    }

    /**
     * Extracts the simple product from a configurable or bundle item.
     * Returns the original product if it's already a simple product.
     *
     * @param ItemInterceptor $item
     * @param ProductInterceptor $product
     * @return ProductInterceptor
     * @throws NoSuchEntityException
     */
    protected function getSimpleProductFromItem($item, $product)
    {
        $options = $item->getProductOptions();
        $buyRequest = $item->getBuyRequest();

        // Check if this is a configurable or bundle product with selected options
        if (!$options) {
            return $product;
        }

        $hasConfigurableOption = $buyRequest->getSelectedConfigurableOption();
        $hasBundleSelection = $buyRequest->getSelectionConfigurableOption();

        if (!$hasConfigurableOption && !$hasBundleSelection) {
            return $product;
        }

        // Verify required product option keys exist
        if (!array_key_exists('attributes_info', $options) || !array_key_exists('simple_sku', $options)) {
            return $product;
        }

        // Load the actual simple product
        if ($hasConfigurableOption) {
            return $this->_productRepository->getById($hasConfigurableOption);
        }

        // Bundle product - get first selection
        $selections = $hasBundleSelection;
        return $this->_productRepository->getById($selections[array_key_first($selections)]);
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
     * Extracts the object code (leading uppercase letters) from SKU.
     * Example: "BLWAbo123" -> "BLW", "AFZ-Test" -> "AFZ"
     *
     * @param string $sku
     * @return string
     */
    protected function getObjectCodeFromSku(string $sku): string
    {
        preg_match('/^[A-Z]+/', $sku, $matches);
        return $matches[0] ?? '';
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
