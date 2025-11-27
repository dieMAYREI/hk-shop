<?php
namespace DieMayrei\Order2Cover\Observer;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\ObserverInterface;
use GuzzleHttp\Client;

class CustomerRegistration implements ObserverInterface
{
    /** @var \Magento\Sales\Model\Order\Interceptor $order */
    protected $_order;

    /** @var array $_request */
    private $_request;

    /** @var StoreManagerInterface */
    protected $_storeManager;

    /** @var CustomerRepositoryInterface */
    protected $_customerRepositoryInterface;

    /** @var OrderRepositoryInterface */
    protected $_orderRepository;

    /**
     * CustomerRegistration constructor.
     * @param  StoreManagerInterface  $storeManager
     * @param  CustomerRepositoryInterface  $customerRepositoryInterface
     * @param  OrderRepositoryInterface  $orderRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepositoryInterface,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->_storeManager = $storeManager;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * @param  Observer  $observer
     */
    public function execute(Observer $observer)
    {
        $order_id = $observer->getData('order_id');
        $this->_order = $this->_orderRepository->get($order_id);
        $this->buildRequest();
        $this->makeRequest();
    }

    public function buildRequest()
    {
        $this->_request['transaction']['timestamp'] = strtotime($this->_order->getCreatedAt());
        $this->_request['transaction']['transaction_id'] = $this->_order->getCustomerId();
        $this->_request['transaction']['ext_system_type'] = 'Webshop';
        $this->_request['transaction']['ext_system_id'] = 'dlv-shop.dlv.de';
        $this->_request['transaction']['ext_system_name'] = 'ext_system_name';
        $this->_request['transaction']['webshop_id'] = $this->_order->getStoreId();
        $this->_request['transaction']['webshop_name'] = $this->_order->getStoreName();
        $this->_request['transaction']['erp_owner'] = 'DLV';
        $this->_request['transaction']['erp_system_id'] = 'M2-DLVSHOP';

        $this->_request['extsyscustomer']['ext_system_id'] = 'dlv-shop.dlv.de';
        $this->_request['extsyscustomer']['ext_customer_nr'] = $this->_order->getCustomerId();
        $this->_request['extsyscustomer']['ext_customer_sub_nr'] = '';
        $this->_request['extsyscustomer']['registration_email'] = $this->_order->getCustomerEmail();
        $this->_request['extsyscustomer']['registration_name'] = $this->_order->getCustomerFirstname().' '.trim($this->_order->getCustomerMiddlename().' ').$this->_order->getCustomerLastname();
        $this->_request['extsyscustomer']['registration_status'] = "REQUEST";
        $this->_request['extsyscustomer']['registration_error_code'] = '';
        $this->_request['extsyscustomer']['registration_info'] = '';

        $this->_request['timestamp'] = strtotime($this->_order->getCreatedAt());
        $this->_request['address']['ext_system_id'] = 'dlv-shop.dlv.de';
        $this->_request['address']['ext_customer_nr'] = $this->_order->getCustomerId();
        $this->_request['address']['ext_customer_sub_nr'] = '';
        $this->_request['address']['erp_system_id'] = 'COVERNET2';
        $this->_request['address']['erp_customer_nr' ] =  $this->_order->getCustomerId();
        $this->_request['address']['erp_customer_sub_nr' ] = '0';
        $this->_request['address']['salutation_code'] = '0';
        $this->_request['address']['title_code'] = '47';
        $this->_request['address']['firstname'] =  $this->_order->getCustomerFirstname();
        $this->_request['address']['lastname'] = $this->_order->getCustomerLastname();
        $this->_request['address']['company'] = '';
        $this->_request['address']['street'] = '';
        $this->_request['address']['street2'] = '';
        $this->_request['address']['postcode'] = '';
        $this->_request['address']['city'] = '';
        $this->_request['address']['country_code'] = '';
        $this->_request['address']['vat_number'] = '';
        $this->_request['address']['email'] = $this->_order->getCustomerEmail();
        $this->_request['address']['phone'] = '';
        $this->_request['address']['mobile'] = '';
        $this->_request['address']['fax'] = '02341234567';
        $this->_request['address']['optin_general'] = '';
        $this->_request['address']['optin_email'] = '';
        $this->_request['address']['optin_phone'] = '';
        $this->_request['address']['optin_letter'] = '';
        $this->_request['address']['address_change_status'] = '';
    }

    public function makeRequest()
    {

        $client = new Client([
            'base_uri' => 'https://87.129.211.14:8081',
            'timeout'  => 20.0,
            'verify' => false, // todo: Maybe trust the Cover cert manually instead of no checking at all
        ]);
        try {
            $res = $client->post(
                '/cover/wrdtest/ecregist',
                [
                    'headers' => [
                        'C_BENUTZER_ID' => 'MAY1WS',
                        'C_PASSWORT' => 'mayWSdlv*!',
                        'C_TRANSFER_ID' => 'M2-DLVSHOP',
                        'C_MANDANT' => '01',
                        'C_ANW' => 'V',
                        'C_KUNDE' => 'dlv',
                        'Accept' => 'application/json',
                    ],
                    'json' => $this->_request,
                ]
            );

            error_log('Cover registration response: ' . $res->getStatusCode());
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response) {
                error_log((string) $response->getBody()->getContents());
                error_log(print_r($response->getHeaders(), true));
                error_log('Cover registration response code: ' . $response->getStatusCode());
            } else {
                error_log('Cover registration BadResponseException without response: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('Cover registration error: ' . get_class($e) . ' - ' . $e->getMessage());
        }
    }
}
