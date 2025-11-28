<?php
/**
 * Copyright © DieMayrei. All rights reserved.
 */
declare(strict_types=1);

namespace DieMayrei\ProductStock\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Store\Model\ScopeInterface;

class ShippingMethods extends AbstractSource implements OptionSourceInterface
{
    private ScopeConfigInterface $scopeConfig;
    private ShippingConfig $shippingConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ShippingConfig $shippingConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->shippingConfig = $shippingConfig;
    }

    /**
     * Get all options - only active shipping methods
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('-- Alle Versandmethoden erlaubt --')],
            ];

            $activeCarriers = $this->shippingConfig->getActiveCarriers();

            foreach ($activeCarriers as $carrierCode => $carrierModel) {
                if ($methods = $carrierModel->getAllowedMethods()) {
                    $carrierTitle = $this->scopeConfig->getValue(
                        'carriers/' . $carrierCode . '/title',
                        ScopeInterface::SCOPE_STORE
                    ) ?: $carrierCode;

                    foreach ($methods as $methodCode => $methodTitle) {
                        $value = $carrierCode . '_' . $methodCode;
                        $label = $carrierTitle . ' - ' . $methodTitle;
                        $this->_options[] = ['value' => $value, 'label' => $label];
                    }
                }
            }
        }
        return $this->_options;
    }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return $this->getAllOptions();
    }
}
