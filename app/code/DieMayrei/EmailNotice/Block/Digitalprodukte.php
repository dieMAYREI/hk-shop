<?php

namespace DieMayrei\EmailNotice\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Digitalprodukte extends Template
{

    protected $productRepository;
    /**
     * @var OrderRepositoryInterface
     */
    private $_orderRepository;

    /**
     * Constructor
     *
     * @param Context $context
     * @param array   $data
     */
    public function __construct(
        Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_product = $productRepository;
        $this->_orderRepository = $orderRepository;
    }

    public function getProductImportantNotes($id)
    {
        if ($this->_product->getById($id)->getEMailText()) {
            return $this->_product->getById($id)->getEMailText();
        }

        return false;
    }


    /**
     * @param $orderId
     * @return false|OrderInterface
     */
    public function getOrderById($orderId)
    {
        try {
            /** @var $order OrderInterface **/
            $order = $this->_orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            return false;
        }
        return  $order;
    }

    /**
     * @param $order_id
     * @return array
     */
    public function getNotes($order_id)
    {
        $notes = [];
        $zeitschriftOrder = false;

        $order = $this->getOrderById($order_id);
        if (!$order) {
            return $notes;
        }
        $items = $order->getItems();
        if (!$items) {
            return $notes;

        };
        foreach ($items as $item) {
            $item_data = $item->getData();
            $id = $item_data['product_id'];
            if ($id) {
                if ($this->getProductSet($id) == 10) {
                    if (!$zeitschriftOrder) {
                        $notes[] = 'Falls Sie Änderungswünsche zu Ihrem bestellten Abo haben, wenden Sie sich bitte an <a href="mailto:kundenservice@hk-verlag.de" target="_blank">kundenservice@hk-verlag.de.</a>';
                        $zeitschriftOrder = true;
                    }
                }
                if ($this->getProductImportantNotes($id)) {
                    $notes[] = $this->getProductImportantNotes($id);
                }
            }
        }

        return $notes;
    }

    /**
     * @param $order_id
     * @return array|mixed
     */
    public function getRedirects($order_id)
    {
        $redirects = [];

        $order = $this->getOrderById($order_id);
        if (!$order) {
            return $redirects;
        }
        $items = $order->getItems();
        if (!$items) {
            return $redirects;

        };
        foreach ($items as $item) {
            $item_data = $item->getData();
            $id = $item_data['product_id'];
            if ($id) {
                if ($this->getDigitalRedirect($id)) {
                    $redirects[] = $this->getDigitalRedirect($id);
                }
            }
        }

        return $redirects;
    }

    public function sayHello()
    {
        return __('Hello World');
    }

    public function getProductSet($id)
    {
        return $this->_product->getById($id)->getAttributeSetId();
    }

    public function getDigitalRedirect($id)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->create('Magento\Checkout\Model\Session');

        if ($customerSession->getAssetProd() == $id) {
            $params = [
                'BuyOriginAssetID' => $customerSession->getAssetId()
            ];

            if ($customerSession->getAssetMag()) {
                $params['BuyOriginMagazineID'] = $customerSession->getAssetMag();
            }

            return '<div style="text-align: center"><a style="display: inline-block;padding:10px 15px;border: 2px solid #00892E;color:#00892E !important;cursor:pointer" target="_blank" rel="nofollow noopener noreferrer" href="http://www.digitalmagazin.de/detail?'.http_build_query($params).'">Zurück zum Artikel</a></div>';
        }
        return false;
    }
}
