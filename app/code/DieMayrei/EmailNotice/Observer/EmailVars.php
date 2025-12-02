<?php

namespace DieMayrei\EmailNotice\Observer;

use DieMayrei\EmailNotice\Traits\FormatEmailVars;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class EmailVars implements ObserverInterface
{
    use FormatEmailVars;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->_logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $transport = $observer->getTransport();
        /** @var Order $order */
        $order = $transport->getData('order');

        if ($order->hasBillingAddressId()) {
            $transport['customBillingAddress'] = $this->getMyCustomBillingAddress($order->getBillingAddress());
            if ($order->hasShippingAddressId()) {
                $transport['customShippingAddress'] = $this->getMyCustomBillingAddress($order->getShippingAddress(), 'shipping');
            } else {
                $transport['customShippingAddress'] = $this->getMyCustomBillingAddress($order->getBillingAddress(), 'shipping');
            }
            
            switch ($order->getBillingAddress()->getPrefix()) {
                case '0':
                    $transport['anrede'] = 'Sehr geehrter Herr ' . $order->getBillingAddress()->getLastname() . ',';
                    break;
                case '1':
                    $transport['anrede'] = 'Sehr geehrte Frau ' . $order->getBillingAddress()->getLastname() . ',';
                    break;
                case '2':
                    $transport['anrede'] = 'Sehr geehrte(r) ' . $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getLastname() . ',';
                    break;
                case '4':
                    $transport['anrede'] = 'Sehr geehrte Familie ' . $order->getBillingAddress()->getLastname() . ',';
                    break;
                case '3':
                    $transport['anrede'] = 'Sehr geehrte(r) ' . $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getLastname() . ',';
                    break;
                default:
                    $transport['anrede'] = 'Sehr geehrte Damen und Herren,';
                    break;
            }
        }

        // Always show delivery address
        $transport['showdelivery'] = 'yes';
    }
}
