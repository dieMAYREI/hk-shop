<?php

declare(strict_types=1);

namespace DieMayrei\SepaPayment\Model\Ui;

use DieMayrei\SepaPayment\Model\Payment\Sepa;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;

class SepaConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey
    ) {
    }

    public function getConfig(): array
    {
        $isActive = $this->scopeConfig->isSetFlag('payment/' . Sepa::PAYMENT_CODE . '/active');
        if (!$isActive) {
            return [];
        }

        $instructions = (string)$this->scopeConfig->getValue('payment/' . Sepa::PAYMENT_CODE . '/instructions');

        return [
            'payment' => [
                'instructions' => [
                    Sepa::PAYMENT_CODE => $instructions,
                ],
                Sepa::PAYMENT_CODE => [
                    'isActive' => $isActive,
                    'title' => (string)$this->scopeConfig->getValue('payment/' . Sepa::PAYMENT_CODE . '/title'),
                    'validationUrl' => $this->urlBuilder->getUrl('diemayrei_sepa/ajax/validate'),
                    'formKey' => $this->formKey->getFormKey(),
                ],
            ],
        ];
    }
}
