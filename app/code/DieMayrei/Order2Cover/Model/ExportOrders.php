<?php


namespace DieMayrei\Order2Cover\Model;

class ExportOrders extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'diemayrei_order2cover_exportorders';

    protected $_cacheTag = 'diemayrei_order2cover_exportorders';

    protected $_eventPrefix = 'diemayrei_order2cover_exportorders';

    protected function _construct()
    {
        $this->_init(\DieMayrei\Order2Cover\Model\ResourceModel\ExportOrders::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}
