<?php


namespace DieMayrei\Order2Cover\Model\ResourceModel;

class ExportOrders extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    ) {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('cover_exported_orders', 'id');
    }
}
