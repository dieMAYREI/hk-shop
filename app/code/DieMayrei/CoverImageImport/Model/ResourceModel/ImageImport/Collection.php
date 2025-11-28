<?php


namespace Diemayrei\CoverImageImport\Model\ResourceModel\ImageImport;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'diemayrei_coverimageimport_imageimport_collection';
    protected $_eventObject = 'imageimport_collection';

  /**
   * Define resource model
   *
   * @return void
   */
    protected function _construct()
    {
        $this->_init(
            \Diemayrei\CoverImageImport\Model\ImageImport::class,
            \Diemayrei\CoverImageImport\Model\ResourceModel\ImageImport::class
        );
    }
}
