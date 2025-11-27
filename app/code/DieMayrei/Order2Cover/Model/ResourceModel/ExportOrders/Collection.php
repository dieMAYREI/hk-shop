<?php


namespace DieMayrei\Order2Cover\Model\ResourceModel\ExportOrders;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'diemayrei_order2cover_exportorders_collection';
    protected $_eventObject = 'exportorders_collection';

  /**
   * Define resource model
   *
   * @return void
   */
    protected function _construct()
    {
        $this->_init(
            \DieMayrei\Order2Cover\Model\ExportOrders::class,
            \DieMayrei\Order2Cover\Model\ResourceModel\ExportOrders::class
        );
    }
}
