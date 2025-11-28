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
     * Get configuration value by path
     *
     * @param string $configPath
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfig(string $configPath, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get cover array (config path => label mapping)
     *
     * @return array
     */
    public function getCoverArray(): array
    {
        return $this->magazineConfig->getConfigPathMapping();
    }

    /**
     * Get label to config mapping
     *
     * @return array
     */
    public function getLabelToConfigMapping(): array
    {
        return $this->magazineConfig->getLabelToConfigMapping();
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
}
