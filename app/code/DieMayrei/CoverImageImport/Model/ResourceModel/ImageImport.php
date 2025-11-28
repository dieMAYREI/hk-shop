<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ImageImport extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('cover_image_import', 'id');
    }
}
