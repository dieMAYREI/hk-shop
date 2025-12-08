<?php

namespace DieMayrei\CheckoutFix\Plugin;

use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Framework\Validator\Exception as ValidatorException;
use Magento\Framework\Validator\Factory as ValidatorFactory;
use Magento\Customer\Model\AddressFactory;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote;

/**
 * Fix missing prefix field during guest checkout address validation
 *
 * Root Cause:
 * In Magento\Quote\Model\CustomerManagement::validateAddresses() (lines 162-173),
 * when a guest checkout is processed, the billing address is copied to a customer
 * address for validation. However, the prefix field is NOT copied, causing
 * validation to fail when prefix is a required field.
 *
 * The core code only copies:
 * - firstname, lastname, street, city, postcode, telephone, countryId, customAttributes
 *
 * But prefix is a regular attribute (not a customAttribute), so it gets lost.
 *
 * This plugin replaces the validation logic for guest checkouts to ensure ALL
 * required fields including prefix are properly copied.
 */
class FixCustomerManagementValidationPlugin
{
    private $customerAddressFactory;
    private $validatorFactory;
    private $addressFactory;

    public function __construct(
        AddressInterfaceFactory $customerAddressFactory,
        ValidatorFactory $validatorFactory,
        AddressFactory $addressFactory
    ) {
        $this->customerAddressFactory = $customerAddressFactory;
        $this->validatorFactory = $validatorFactory;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Fix prefix field not being copied during guest checkout validation
     *
     * @param CustomerManagement $subject
     * @param callable $proceed
     * @param Quote $quote
     * @return void
     * @throws ValidatorException
     */
    public function aroundValidateAddresses(CustomerManagement $subject, callable $proceed, Quote $quote)
    {
        // Only override for guest checkouts
        if (!$quote->getCustomerIsGuest()) {
            return $proceed($quote);
        }

        // For guest checkout, replicate the core logic but include prefix
        $billingAddress = $quote->getBillingAddress();
        $customerAddress = $this->customerAddressFactory->create();

        // Copy all the standard fields (from core code)
        $customerAddress->setFirstname($billingAddress->getFirstname());
        $customerAddress->setLastname($billingAddress->getLastname());
        $customerAddress->setStreet($billingAddress->getStreet());
        $customerAddress->setCity($billingAddress->getCity());
        $customerAddress->setPostcode($billingAddress->getPostcode());
        $customerAddress->setTelephone($billingAddress->getTelephone());
        $customerAddress->setCountryId($billingAddress->getCountryId());
        $customerAddress->setCustomAttributes($billingAddress->getCustomAttributes());

        // FIX: Also copy prefix (and other missing fields)
        if ($billingAddress->getPrefix()) {
            $customerAddress->setPrefix($billingAddress->getPrefix());
        }
        if ($billingAddress->getSuffix()) {
            $customerAddress->setSuffix($billingAddress->getSuffix());
        }
        if ($billingAddress->getMiddlename()) {
            $customerAddress->setMiddlename($billingAddress->getMiddlename());
        }
        if ($billingAddress->getCompany()) {
            $customerAddress->setCompany($billingAddress->getCompany());
        }
        if ($billingAddress->getFax()) {
            $customerAddress->setFax($billingAddress->getFax());
        }
        // Region needs to be copied as an object, not a string
        if ($billingAddress->getRegionId()) {
            $customerAddress->setRegionId($billingAddress->getRegionId());
        }
        if ($billingAddress->getRegion() && $billingAddress->getRegion() instanceof \Magento\Customer\Api\Data\RegionInterface) {
            $customerAddress->setRegion($billingAddress->getRegion());
        }

        // Perform validation
        $validator = $this->validatorFactory->createValidator('customer_address', 'save');
        $addressModel = $this->addressFactory->create();
        $addressModel->updateData($customerAddress);

        if (!$validator->isValid($addressModel)) {
            throw new ValidatorException(
                null,
                null,
                $validator->getMessages()
            );
        }

        return null;
    }
}
