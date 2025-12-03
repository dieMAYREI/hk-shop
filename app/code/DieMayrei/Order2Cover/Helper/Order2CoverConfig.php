<?php

namespace DieMayrei\Order2Cover\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;

class Order2CoverConfig extends AbstractHelper
{
    /**
     * @var State
     */
    protected $appState;

    /**
     * @param Context $context
     * @param State $appState
     */
    public function __construct(
        Context $context,
        State $appState
    ) {
        parent::__construct($context);
        $this->appState = $appState;
    }

    /**
     * Get config value by path
     *
     * @param string $config_path
     * @return mixed
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the appropriate Cover base URL based on environment
     *
     * @return string
     */
    public function getCoverBaseUrl(): string
    {
        if ($this->isProduction()) {
            return (string) $this->getConfig('diemayrei/order_2_cover/cover_base_url');
        }

        return (string) $this->getConfig('diemayrei/order_2_cover/cover_base_url_staging');
    }

    /**
     * Check if the application is in production mode
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        try {
            return $this->appState->getMode() === State::MODE_PRODUCTION;
        } catch (\Exception $e) {
            return false;
        }
    }
}
