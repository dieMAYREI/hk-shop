<?php


namespace Diemayrei\CoverImageImport\Model\ResourceModel;

class ImageImport extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    ) {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('cover_image_import', 'id');
    }
}
