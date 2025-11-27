<?php

namespace DieMayrei\Order2Cover\Cron;

use DieMayrei\Order2Cover\Model\ExportOrders;
use DieMayrei\Order2Cover\Model\ExportOrdersFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\App\State;
use GuzzleHttp\Psr7\Message;
use DieMayrei\Order2Cover\Helper\Order2CoverConfig;

class QueueWorker
{
    /** @var ExportOrdersFactory  */
    protected $_exportOrdersFactory;
    /** @var ExportOrdersFactory  */
    protected $_order2CoverConfig;
    /** @var State  */
    protected $_appState;
    /** @var Client */
    private Client $_client;

    public function __construct(
        ExportOrdersFactory $exportOrdersFactory,
        Order2CoverConfig $order2CoverConfig,
        State $appState
    ) {

        $this->_order2CoverConfig = $order2CoverConfig;
        $this->_exportOrdersFactory = $exportOrdersFactory;
        $this->_appState = $appState;
        $this->_client =  new Client([
            'base_uri' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_base_url'),
            'timeout'  => 30.0,
            'verify' => false, // todo: Maybe trust the Cover cert manually instead of no checking at all
            'headers' => [
                'C_BENUTZER_ID' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_benutzer_id'),
                'C_PASSWORT' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_password'),
                'C_TRANSFER_ID' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_transfer_id'),
                'C_MANDANT' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_mandant'),
                'C_ANW' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_anwendung'),
                'C_KUNDE' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_kunde'),
                'Accept'=> 'application/json',
                'Content-Type'=> 'application/json',
            ],
        ]);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function execute()
    {
        $orders = $this->getOrders();
        if (!$orders) {
            return;
        }

        foreach ($orders as $order) {
            try {
                $result = $this->makeRequest($order);
                $order['response'] = Message::toString($result);
                $order->save();
            } catch (\Throwable $error) {
                error_log($error->getMessage());
                if ($error instanceof GuzzleException) {
                    try {
                        $result = $this->makeRequest($order);
                        $order['response'] = Message::toString($result);
                        $order->save();
                    } catch (\Throwable $error) {
                        error_log($error->getMessage());
                        mail('aboshop@dlv.de', 'Bestellung '.$order->getId(), 'Die Bestellung konnte auch mit 2 Versuchen nicht an Cover übertragen werden');
                    }
                }
            }
        }
    }

    /** @return array|ExportOrders[] */
    protected function getOrders(): array
    {
        /** @var \DieMayrei\Order2Cover\Model\ResourceModel\ExportOrders\Collection $collection */
        $collection = $this->_exportOrdersFactory->create()->getCollection();
        $collection->addFieldToSelect('order_id');
        $collection->addFieldToSelect('payload');
        $collection->addFieldToSelect('id');
        $collection->addFieldToFilter('response', ['null' => true ]);
        $collection->setOrder('id', 'asc');
        return iterator_to_array($collection);
    }

    /**
     * @param  ExportOrders  $order
     * @return Response
     */
    public function makeRequest(ExportOrders $order): Response
    {
            return $this->_client->post(
                'ecorder',
                [
                    'body' => $order['payload'],
                    'http_errors' => false,
                ]
            );
    }
}
