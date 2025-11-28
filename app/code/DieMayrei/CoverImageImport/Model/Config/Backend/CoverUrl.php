<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class CoverUrl extends Value
{
    /**
     * Validate URL before saving
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Please enter a valid URL for the cover image.')
            );
        }

        return parent::beforeSave();
    }
}
