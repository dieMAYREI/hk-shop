<?php

namespace DieMayrei\EmailNotice\Observer;

use DieMayrei\EmailNotice\Traits\FormatEmailVars;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use DieMayrei\CustomCoupons\Model\CustomCouponsFactory;

class EmailVars implements ObserverInterface
{

    use FormatEmailVars;

    /**
     * @var ObjectManager ObjectManager
     */
    protected $objectManager;

    protected $customCouponsFactory;

    protected $_logger;

    public function __construct(
        CustomCouponsFactory $customCouponsFactory,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->objectManager = ObjectManager::getInstance();
        $this->customCouponsFactory = $customCouponsFactory;
        $this->_logger = $logger;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $geschenk = true;
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
            switch ($order->getBillingAddress()->getPrefix()):
                case '0':
                    $transport['anrede'] = 'Sehr geehrter Herr '.$order->getBillingAddress()->getLastname().',';
                    break;
                case '1':
                    $transport['anrede'] = 'Sehr geehrte Frau '.$order->getBillingAddress()->getLastname().',';
                    break;
                case '2':
                    $transport['anrede'] = 'Sehr geehrte(r) '.$order->getBillingAddress()->getFirstname().' '.$order->getBillingAddress()->getLastname().',';
                    break;
                case '4':
                    $transport['anrede'] = 'Sehr geehrte Familie '.$order->getBillingAddress()->getLastname().',';
                    break;
                case '3':
                    $transport['anrede'] = 'Sehr geehrte(r) '.$order->getBillingAddress()->getFirstname().' '.$order->getBillingAddress()->getLastname().',';
                    break;
            endswitch;
        }

        foreach ($order->getItems() as $item) {
            /** @var \Magento\Catalog\Model\Product\Interceptor $item */
            $product = $this->objectManager->create('\Magento\Catalog\Model\Product')->load($item->getProductId());

            if ($product->getSku() == 'PI-Digitalmagazin-PI25DMHPEPH-13') {
                $transport['HuntOnDemandCode'] = $this->getNewCustomCoupon($order->getId(), 'Hunt on Demand');

            }

            if ($product->getAttributeSetId() == 11) {
                $geschenk = false;
                continue;
            }

            if ($product->getAttributeSetId() == 10) {
                $variante = $product->getAttributeText('abovarianten');
                if (!substr_count($variante, 'Geschenk') > 0) {
                    $geschenk = false;
                    continue;
                }
            }
        }

        if ($geschenk) {
            $transport['showdelivery'] = '';
        } else {
            $transport['showdelivery'] = 'yes';
        }
    }

    protected function getNewCustomCoupon($orderId, $campaign)
    {

        $checkIfOrderIdisUsesd = $this->customCouponsFactory
            ->create()
            ->getCollection()
            ->addFieldToFilter('used', $orderId)
            ->addFieldToFilter('campain', $campaign)
            ->getFirstItem();
        $this->_logger->debug('CheckifUser Code:'.' '.$checkIfOrderIdisUsesd->getCode());

        if (empty($checkIfOrderIdisUsesd->getCode())) {
            $coupon = $this->customCouponsFactory
                ->create()
                ->getCollection()
                ->addFieldToFilter('used', 0)
                ->addFieldToFilter('campain', $campaign)
                ->getFirstItem();
                $couponCode = $coupon->getCode();
                $coupon->setUsed($orderId);
                $coupon->save();
            $this->_logger->debug('Used Code:'.' '.$couponCode);
        } else {
            $couponCode = $checkIfOrderIdisUsesd->getCode();
            $this->_logger->debug('Used Code:'.' '.$couponCode);
        }

        if ($couponCode) {
            return $couponCode;
        }
        return '';
    }

    protected function getMyCustomBillingAddress(\Magento\Sales\Api\Data\OrderAddressInterface $billingAddress, $type = 'billing')
    {
        $address = '';
        switch ($billingAddress->getPrefix()) {
            case 2:
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix())." ";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
                break;
            case 3:
                $address .= 'Firma ';
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix()).'';
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }

                break;
                break;
            case 4:
                $address .= 'Familie ';
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix())."";
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            case '0':
                    $address .= 'Herr ';
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix())."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            case 1:
                $address .= 'Frau ';
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix())."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            default:
                if ($billingAddress->getPrefix()) {
                    $address .= $billingAddress->getPrefix().' ';
                }
                if ($billingAddress->getSuffix()) {
                    $address .= $this->getTitle($billingAddress->getSuffix())."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
        }

        if ($billingAddress->getStreet()) {
            $streetLines = $billingAddress->getStreet();
            $counter = 0;
            foreach ($streetLines as $lineNumber => $lineValue) {
                if ($lineValue != '') {
                    $address .= $lineValue.' ';
                    if ($counter == 1) {
                        $address .= "\r\n";
                    }
                    if ($counter == 3) {
                        $address .= "\r\n";
                    }
                }
                $counter ++;
            }
        }
        if ($billingAddress->getCity()) {
            $address .= $billingAddress->getCity().', ';
        }
        if ($billingAddress->getRegion()) {
            $address .= $billingAddress->getRegion().', ';
        }
        if ($billingAddress->getPostcode()) {
            $address .= $billingAddress->getPostcode()."\r\n";
        }
        if ($billingAddress->getCountryId()) {
            $country = $this->objectManager->create('\Magento\Directory\Model\Country')->load($billingAddress->getCountryId())->getName();
            $address .= $country."\r\n";
        }
        if ($type=='billing') {
            if ($billingAddress->getTelephone()) {
                $address .= "T:  <a href=\"tel:".$billingAddress->getTelephone()."\">".$billingAddress->getTelephone()."</a>\r\n";
            }
            if ($billingAddress->getFax()) {
                $address .= "F: ".$billingAddress->getFax()."\r\n";
            }
            if ($billingAddress->getVatId()) {
                $address .= "VAT: ".$billingAddress->getVatId()."\r\n";
            }
        }
        return $address;
    }
}
