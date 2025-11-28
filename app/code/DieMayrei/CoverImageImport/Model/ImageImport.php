<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class ImageImport extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'diemayrei_coverimageimport';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'diemayrei_coverimageimport';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\ImageImport::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
