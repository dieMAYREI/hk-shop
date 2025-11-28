<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Helper;

use DieMayrei\CoverImageImport\Model\MagazineConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class CoverImageImport extends AbstractHelper
{
    private MagazineConfig $magazineConfig;

    public function __construct(
        Context $context,
        MagazineConfig $magazineConfig
    ) {
        parent::__construct($context);
        $this->magazineConfig = $magazineConfig;
    }

    /**
     * Get all configured cover URLs with their keys
     *
     * @return array ['key' => 'url', ...]
     */
    public function getAllCoverUrls(): array
    {
        return $this->magazineConfig->getAllCoverUrls();
    }

    /**
     * Get cover URL for a specific key
     *
     * @param string $coverKey
     * @return string|null
     */
    public function getCoverUrl(string $coverKey): ?string
    {
        return $this->magazineConfig->getCoverUrl($coverKey);
    }

    /**
     * Get label for a cover key
     *
     * @param string $coverKey
     * @return string
     */
    public function getLabel(string $coverKey): string
    {
        return $this->magazineConfig->getLabel($coverKey);
    }

    /**
     * Get all magazine options for dropdowns
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        return $this->magazineConfig->getAllOptions();
    }

    /**
     * Check if cover key is valid
     *
     * @param string $coverKey
     * @return bool
     */
    public function isValidCoverKey(string $coverKey): bool
    {
        return $this->magazineConfig->isValidCoverKey($coverKey);
    }
}
