<?php

declare(strict_types=1);

namespace DieMayrei\SepaPayment\Model\Payment;

use DieMayrei\SepaPayment\Model\Validator\IbanValidator;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;

class Sepa extends AbstractMethod
{
    public const PAYMENT_CODE = 'diemayrei_sepa';
    public const FIELD_ACCOUNT_HOLDER = 'account_holder';
    public const FIELD_IBAN = 'iban';
    public const FIELD_BIC = 'bic';
    public const FIELD_BANK_NAME = 'bank_name';

    /**
     * @var string
     */
    protected $_code = self::PAYMENT_CODE;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var string
     */
    protected $_infoBlockType = \DieMayrei\SepaPayment\Block\Info\Sepa::class;

    /**
     * @var IbanValidator
     */
    private IbanValidator $ibanValidator;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        IbanValidator $ibanValidator,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->ibanValidator = $ibanValidator;
    }

    /**
     * Assign additional payment data submitted at checkout.
     */
    public function assignData(DataObject $data): Sepa
    {
        parent::assignData($data);
        $additionalData = $data->getData('additional_data') ?? [];

        if (!is_array($additionalData) && $data->getDataByKey('additional_data')) {
            $additionalData = $data->getDataByKey('additional_data');
        }

        $infoInstance = $this->getInfoInstance();
        $infoInstance->setAdditionalInformation(
            self::FIELD_ACCOUNT_HOLDER,
            $this->cleanString((string)($additionalData[self::FIELD_ACCOUNT_HOLDER] ?? ''))
        );
        $infoInstance->setAdditionalInformation(
            self::FIELD_IBAN,
            $this->normalizeIban((string)($additionalData[self::FIELD_IBAN] ?? ''))
        );
        $infoInstance->setAdditionalInformation(
            self::FIELD_BIC,
            $this->cleanString((string)($additionalData[self::FIELD_BIC] ?? ''))
        );
        $infoInstance->setAdditionalInformation(
            self::FIELD_BANK_NAME,
            $this->cleanString((string)($additionalData[self::FIELD_BANK_NAME] ?? ''))
        );

        return $this;
    }

    /**
     * Validate all SEPA-related fields and re-check the IBAN via the remote service.
     *
     * @throws LocalizedException
     */
    public function validate(): AbstractModel
    {
        $infoInstance = $this->getInfoInstance();
        $accountHolder = (string)$infoInstance->getAdditionalInformation(self::FIELD_ACCOUNT_HOLDER);
        $iban = $this->normalizeIban((string)$infoInstance->getAdditionalInformation(self::FIELD_IBAN));

        if ($accountHolder === '') {
            throw new LocalizedException(__('Bitte geben Sie den Kontoinhaber an.'));
        }

        if ($iban === '') {
            throw new LocalizedException(__('Bitte geben Sie eine gültige IBAN ein.'));
        }

        $validationResult = $this->ibanValidator->validate($iban);
        if (!($validationResult['valid'] ?? false)) {
            $messages = $validationResult['messages'] ?? [];
            $message = (string)reset($messages) ?: __('Die IBAN konnte nicht verifiziert werden.');
            throw new LocalizedException(__($message));
        }

        $bankData = $validationResult['bankData'] ?? [];
        if (!empty($bankData['bic'])) {
            $infoInstance->setAdditionalInformation(self::FIELD_BIC, $this->cleanString((string)$bankData['bic']));
        }

        if (!empty($bankData['name'])) {
            $infoInstance->setAdditionalInformation(self::FIELD_BANK_NAME, $this->cleanString((string)$bankData['name']));
        }

        $bic = (string)$infoInstance->getAdditionalInformation(self::FIELD_BIC);
        if ($bic === '') {
            throw new LocalizedException(__('Die BIC konnte nicht ermittelt werden. Bitte prüfen Sie Ihre Eingaben.'));
        }

        $bankName = (string)$infoInstance->getAdditionalInformation(self::FIELD_BANK_NAME);
        if ($bankName === '') {
            throw new LocalizedException(__('Der Bankname konnte nicht ermittelt werden. Bitte prüfen Sie Ihre Eingaben.'));
        }

        $infoInstance->setAdditionalInformation(self::FIELD_IBAN, $iban);

        return parent::validate();
    }

    private function normalizeIban(string $iban): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        return $iban;
    }

    private function cleanString(string $value): string
    {
        return trim($value);
    }
}
