<?php

/**
 * Das hier ist ein Duplikat von Cron/QueueWorker.php
 * Auf dem Staging System darf der Cron nicht laufen wegen Cover.
 */

namespace DieMayrei\Order2Cover\Controller\Dev;

use DieMayrei\Order2Cover\Model\ExportOrders;
use DieMayrei\Order2Cover\Model\ExportOrdersFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use DieMayrei\Order2Cover\Helper\Order2CoverConfig;

class InTimeSubmit
{
    /**
     * @var ExportOrdersFactory
     */
    protected $_exportOrdersFactory;

    /**
     * @var Order2CoverConfig
     */
    protected $_order2CoverConfig;

    /**
     * @var Client
     */
    protected $_client;

    /**
     * InTimeSubmit constructor.
     *
     * @param ExportOrdersFactory $exportOrdersFactory
     * @param Order2CoverConfig $order2CoverConfig
     */
    public function __construct(
        ExportOrdersFactory $exportOrdersFactory,
        Order2CoverConfig $order2CoverConfig
    ) {
        $this->_order2CoverConfig = $order2CoverConfig;
        $this->_exportOrdersFactory = $exportOrdersFactory;
        $this->_client = new Client([
            'base_uri' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_base_url'),
            'timeout' => 30.0,
            'verify' => false, // todo: Maybe trust the Cover cert manually instead of no checking at all
            'headers' => [
                'C_BENUTZER_ID' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_benutzer_id'),
                'C_PASSWORT' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_password'),
                'C_TRANSFER_ID' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_transfer_id'),
                'C_MANDANT' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_mandant'),
                'C_ANW' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_anwendung'),
                'C_KUNDE' => $this->_order2CoverConfig->getConfig('diemayrei/order_2_cover/cover_kunde'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Führt die Übertragung der Bestellungen an Cover aus.
     *
     * @return $this|null
     */
    public function execute()
    {
        $orders = $this->getOrders();
        if (empty($orders)) {
            return null;
        }

        foreach ($orders as $order) {
            $this->transmitOrder($order);
        }

        return $this;
    }

    /**
     * Überträgt eine einzelne Bestellung an Cover.
     *
     * @param ExportOrders $order
     * @return void
     */
    protected function transmitOrder(ExportOrders $order): void
    {
        try {
            $result = $this->makeRequest($order);
            $order->setData('response', Message::toString($result));
            $order->save();
        } catch (\Throwable $error) {
            $this->logError($order, $error);
        }
    }

    /**
     * Loggt Fehler bei der Übertragung.
     *
     * @param ExportOrders $order
     * @param \Throwable $error
     * @return void
     */
    protected function logError(ExportOrders $order, \Throwable $error): void
    {
        $errorLogPath = BP . '/var/log/order2cover.log';
        $message = sprintf(
            "[%s] Order %s: %s\n",
            date('Y-m-d H:i:s'),
            $order->getData('order_id'),
            $error->getMessage()
        );
        error_log($message, 3, $errorLogPath);
    }

    /**
     * Holt Bestellungen ohne Response für die Übertragung.
     *
     * @return array|ExportOrders[]
     */
    protected function getOrders(): array
    {
        /** @var \DieMayrei\Order2Cover\Model\ResourceModel\ExportOrders\Collection $collection */
        $collection = $this->_exportOrdersFactory->create()->getCollection();
        $collection->addFieldToSelect('order_id');
        $collection->addFieldToSelect('payload');
        $collection->addFieldToSelect('id');
        $collection->addFieldToFilter('response', ['null' => true]);
        $collection->setOrder('id', 'desc');
        $collection->setPageSize(1);
        return iterator_to_array($collection);
    }

    /**
     * Sendet eine Bestellung an die Cover-API.
     *
     * @param ExportOrders $order
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
