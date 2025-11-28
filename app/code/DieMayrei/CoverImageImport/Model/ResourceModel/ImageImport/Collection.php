<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model\ResourceModel\ImageImport;

use DieMayrei\CoverImageImport\Model\ImageImport;
use DieMayrei\CoverImageImport\Model\ResourceModel\ImageImport as ImageImportResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'diemayrei_coverimageimport_collection';
    protected $_eventObject = 'imageimport_collection';

    protected function _construct(): void
    {
        $this->_init(ImageImport::class, ImageImportResource::class);
    }
}
