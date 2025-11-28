<?php


namespace Diemayrei\CoverImageImport\Model;

class ImageImport extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'diemayrei_coverimageimport_imageimport';

    protected $_cacheTag = 'diemayrei_coverimageimport_imageimport';

    protected $_eventPrefix = 'diemayrei_coverimageimport_imageimport';

    protected function _construct()
    {
        $this->_init(\Diemayrei\CoverImageImport\Model\ResourceModel\ImageImport::class);
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
