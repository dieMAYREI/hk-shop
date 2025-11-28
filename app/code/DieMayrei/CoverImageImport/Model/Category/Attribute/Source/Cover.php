<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model\Category\Attribute\Source;

use DieMayrei\CoverImageImport\Model\MagazineConfig;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class Cover extends AbstractSource
{
    private MagazineConfig $magazineConfig;

    public function __construct(MagazineConfig $magazineConfig)
    {
        $this->magazineConfig = $magazineConfig;
    }

    /**
     * @inheritdoc
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = $this->magazineConfig->getAllOptions();
        }
        return $this->_options;
    }
}
