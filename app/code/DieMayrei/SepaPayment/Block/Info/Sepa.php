<?php

declare(strict_types=1);

namespace DieMayrei\SepaPayment\Block\Info;

use DieMayrei\SepaPayment\Model\Payment\Sepa as SepaMethod;
use Magento\Payment\Block\Info;
use Magento\Framework\DataObject;

class Sepa extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Magento_Payment::info/default.phtml';

    /**
     * Append SEPA fields to the payment info block (admin + pdf).
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if ($this->_paymentSpecificInformation !== null) {
            return $this->_paymentSpecificInformation;
        }

        $transport = $transport ?: new DataObject();
        $info = $this->getInfo();

        $data = [
            (string)__('Kontoinhaber') => $info->getAdditionalInformation(SepaMethod::FIELD_ACCOUNT_HOLDER),
            (string)__('IBAN') => $info->getAdditionalInformation(SepaMethod::FIELD_IBAN),
            (string)__('BIC') => $info->getAdditionalInformation(SepaMethod::FIELD_BIC),
            (string)__('Bankname') => $info->getAdditionalInformation(SepaMethod::FIELD_BANK_NAME),
        ];

        foreach ($data as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $transport->setData($label, $value);
        }

        return $this->_paymentSpecificInformation = parent::_prepareSpecificInformation($transport);
    }
}
