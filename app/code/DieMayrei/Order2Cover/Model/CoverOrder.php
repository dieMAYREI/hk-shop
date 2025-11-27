<?php

namespace DieMayrei\Order2Cover\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item;
use Psr\Log\LoggerInterface;

class CoverOrder
{
  /**
   * @var array<string, mixed>
   */
    private $configs = [
    'timestamp' => '',
    'transaction_id' => 'The same Unique transaction-ID of registration by calling system',
    'ext_system_type' => 'WEBSHOP',
    'ext_system_id' => 'all_dlv_prod',
    'ext_system_name' => 'dlv-shop.de-prod',
    'webshop_id' => 'main_bas',
    'webshop_name' => 'main-bas-site',
    'erp_owner' => 'IMS',
    'erp_system_id' => 'COVERNET2',
    ];

  /**
   * @var array<int, array<string, mixed>>
   */
    private $ordersForCover = [];

  /**
   * @var ProductRepositoryInterface
   */
    private $productRepository;

  /**
   * @var LoggerInterface
   */
    private $logger;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

  /**
   * Prepare order data for Cover export.
   *
   * @param Order $order
   * @return array<string, mixed>
   */
    public function build(Order $order): array
    {
        $orderId = (int) $order->getId();
        $this->ordersForCover[$orderId] = [];

        $this->collectBillingAddress($order);
        $this->collectShippingAddress($order);
        $this->collectPayment($order);
        $this->collectCommonData($order);
        $this->collectTransaction($order);
        $this->collectItems($order);

        return $this->ordersForCover[$orderId];
    }

  /**
   * Collect billing address data.
   *
   * @param Order $order
   * @return void
   */
    private function collectBillingAddress(Order $order): void
    {
        $billingAddress = $order->getBillingAddress();
        if (!$billingAddress instanceof Address) {
            $this->logger->warning('Order missing billing address.', ['order_id' => $order->getId()]);
            return;
        }

        $this->ordersForCover[$order->getId()]['addresses']['billing'] = [
        'timestamp' => $this->formatDate($order->getCreatedAt()),
        'ext_system_id' => 'all_dlv_prod',
        'ext_customer_nr' => $billingAddress->getCustomerId(),
        'ext_customer_sub_nr' => $billingAddress->getCustomerAddressId(),
        'erp_system_id' => $this->getConfig('erp_system_id'),
        'erp_customer_nr' => '',
        'erp_customer_sub_nr' => '',
        'salutation_code' => $billingAddress->getPrefix(),
        'title_code' => $billingAddress->getSuffix(),
        'firstname' => $billingAddress->getFirstname(),
        'lastname' => $billingAddress->getLastname(),
        'company' => $billingAddress->getCompany(),
        'country_code' => $billingAddress->getCountryId(),
        'street' => trim((string) $billingAddress->getStreetLine(1) . ' ' . (string) $billingAddress->getStreetLine(2)),
        'street2' => $billingAddress->getStreetLine(3),
        'postcode' => $billingAddress->getPostcode(),
        'city' => $billingAddress->getCity(),
        'vat_number' => $billingAddress->getVatId(),
        'email' => $billingAddress->getEmail(),
        'phone' => $billingAddress->getTelephone(),
        ];
    }

  /**
   * Collect shipping address data.
   *
   * @param Order $order
   * @return void
   */
    private function collectShippingAddress(Order $order): void
    {
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress instanceof Address) {
            $this->logger->warning('Order missing shipping address.', ['order_id' => $order->getId()]);
            return;
        }

        $this->ordersForCover[$order->getId()]['addresses']['shipping'] = [
        'timestamp' => $this->formatDate($order->getCreatedAt()),
        'ext_system_id' => 'all_dlv_prod',
        'ext_customer_nr' => $shippingAddress->getCustomerId(),
        'ext_customer_sub_nr' => $shippingAddress->getCustomerAddressId(),
        'erp_system_id' => $this->getConfig('erp_system_id'),
        'erp_customer_nr' => '',
        'erp_customer_sub_nr' => '',
        'salutation_code' => $shippingAddress->getPrefix(),
        'title_code' => $shippingAddress->getSuffix(),
        'firstname' => $shippingAddress->getFirstname(),
        'lastname' => $shippingAddress->getLastname(),
        'company' => $shippingAddress->getCompany(),
        'country_code' => $shippingAddress->getCountryId(),
        'street' => $shippingAddress->getStreetLine(1),
        'street2' => $shippingAddress->getStreetLine(2),
        'postcode' => $shippingAddress->getPostcode(),
        'city' => $shippingAddress->getCity(),
        'vat_number' => $shippingAddress->getVatId(),
        'email' => $shippingAddress->getEmail(),
        'phone' => $shippingAddress->getTelephone(),
        ];
    }

  /**
   * Collect payment data.
   *
   * @param Order $order
   * @return void
   */
    private function collectPayment(Order $order): void
    {
        $payment = [];
        $method = $order->getPayment() ? $order->getPayment()->getMethod() : null;

        if ($method === 'checkmo') {
            $payment = [
            'payment_provider' => 'ERP',
            'payment_type_ext' => 'F',
            'currency' => $order->getOrderCurrency()->toString(),
            'transaction_date' => $this->formatDate($order->getCreatedAt()),
            ];
        } elseif ($method === 'debitpayment') {
            $additionalInformation = (array) $order->getPayment()->getAdditionalInformation();
            $payment = [
            'payment_provider' => 'ERP',
            'payment_type_ext' => 'C',
            'currency' => $order->getOrderCurrency()->toString(),
            'iban' => $additionalInformation['additional_data']['iban'] ?? null,
            'transaction_date' => $this->formatDate($order->getCreatedAt()),
            'sepa_mandate_granted' => true,
            ];
        }

        if (!empty($payment)) {
            $this->ordersForCover[$order->getId()]['payment'] = $payment;
        }
    }

  /**
   * Collect common order data.
   *
   * @param Order $order
   * @return void
   */
    private function collectCommonData(Order $order): void
    {
        $this->ordersForCover[$order->getId()] = array_merge(
            $this->ordersForCover[$order->getId()],
            [
            'order_number' => $order->getRealOrderId(),
            'order_id_unique' => $order->getId(),
            'order_date' => $this->formatDate($order->getCreatedAt()),
            'total_amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrency()->toString(),
            ]
        );
    }

  /**
   * Collect transaction data.
   *
   * @param Order $order
   * @return void
   */
    private function collectTransaction(Order $order): void
    {
        $this->ordersForCover[$order->getId()]['transaction'] = [
        'timestamp' => $this->getConfig('timestamp'),
        'transaction_id' => $this->getConfig('transaction_id'),
        'ext_system_type' => $this->getConfig('ext_system_type'),
        'ext_system_id' => $this->getConfig('ext_system_id'),
        'ext_system_name' => $this->getConfig('ext_system_name'),
        'webshop_id' => $this->getConfig('webshop_id'),
        'webshop_name' => $this->getConfig('webshop_name'),
        'erp_owner' => $this->getConfig('erp_owner'),
        'erp_system_id' => $this->getConfig('erp_system_id'),
        ];
    }

  /**
   * Collect order items data.
   *
   * @param Order $order
   * @return void
   */
    private function collectItems(Order $order): void
    {
        $items = [];

        foreach ($order->getAllItems() as $item) {
            if (!$item instanceof Item) {
                continue;
            }

            try {
                $product = $this->productRepository->getById((int) $item->getProductId());
            } catch (\Exception $exception) {
                $this->logger->error('Failed to load product for Cover order export.', [
                'order_item_id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'exception' => $exception,
                ]);
                continue;
            }

            $attributeSetId = (int) $product->getAttributeSetId();
            if ($attributeSetId === 10) {
                $items[] = $this->prepareSubscriptionItem($item, $product);
            }
        }

        if ($items) {
            $this->ordersForCover[$order->getId()]['items'] = $items;
        }
    }

  /**
   * Prepare subscription order item data.
   *
   * @param Item $item
   * @param Product $product
   * @return array<string, mixed>
   */
    private function prepareSubscriptionItem(Item $item, Product $product): array
    {
        return [
        'type' => 'A',
        'quantity' => (float) $item->getQtyOrdered(),
        'campaign_code' => $product->getData('cover_campaign_id'),
        'campaign_subcode' => $product->getData('cover_campaign_subcode'),
        'campaign_offer_number' => $product->getData('cover_campaign_offer_number'),
        'abo_type' => $product->getData('cover_abo_type'),
        'object_id' => $product->getData('cover_object_id'),
        'edition_id' => $product->getData('cover_edition_id'),
        'price' => (float) $item->getPrice(),
        'start_date' => $this->formatDate($product->getData('cover_start_date')), // Placeholder mapping
        'subscription_ext_id' => $product->getData('cover_subscription_ext_id'),
        ];
    }

  /**
   * Retrieve configuration value.
   *
   * @param string $key
   * @return mixed
   */
    protected function getConfig(string $key)
    {
        return $this->configs[$key] ?? null;
    }

  /**
   * Set configuration value.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
    protected function setConfig(string $key, $value): void
    {
        $this->configs[$key] = $value;
    }

  /**
   * Format datetime string for Cover export.
   *
   * @param mixed $date
   * @return string|null
   */
    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        return date('d.m.Y', strtotime((string) $date));
    }
}
