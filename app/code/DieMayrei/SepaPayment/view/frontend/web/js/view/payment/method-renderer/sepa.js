define([
    'ko',
    'jquery',
    'mage/storage',
    'Magento_Checkout/js/view/payment/default',
    'mage/translate'
], function (ko, $, storage, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'DieMayrei_SepaPayment/payment/sepa',
            accountHolder: '',
            iban: '',
            bic: '',
            bankName: '',
            ibanStatusMessage: '',
            ibanValidated: false,
            isIbanLoading: false,
            accountHolderError: '',
            ibanError: '',
            bicError: '',
            bankNameError: '',
            ibanValidationDelay: 800,
            minIbanLength: 6
        },

        initialize: function () {
            this._super();
            this.ibanValidationTimeout = null;

            return this;
        },

        initObservable: function () {
            this._super()
                .observe([
                    'accountHolder',
                    'iban',
                    'bic',
                    'bankName',
                    'ibanStatusMessage',
                    'ibanValidated',
                    'isIbanLoading',
                    'accountHolderError',
                    'ibanError',
                    'bicError',
                    'bankNameError'
                ]);

            var self = this;
            this.iban.subscribe(function (value) {
                self.ibanValidated(false);
                self.ibanStatusMessage('');
                self.resetBankData();
                self.scheduleIbanValidation(value);
            });

            return this;
        },

        getInstructions: function () {
            return window.checkoutConfig.payment.instructions[this.item.method] || '';
        },

        getData: function () {
            return {
                method: this.item.method,
                additional_data: {
                    account_holder: this.accountHolder(),
                    iban: this.cleanIban(this.iban()),
                    bic: this.bic(),
                    bank_name: this.bankName()
                }
            };
        },

        validate: function () {
            var parentValidation = this._super();

            return parentValidation && this.validateForm();
        },

        validateForm: function () {
            var isValid = true;
            this.resetFieldErrors();

            if (!this.accountHolder()) {
                this.accountHolderError($.mage.__('Bitte geben Sie den Kontoinhaber ein.'));
                isValid = false;
            }

            if (!this.iban()) {
                this.ibanError($.mage.__('Bitte geben Sie eine IBAN ein.'));
                isValid = false;
            } else if (!this.ibanValidated()) {
                this.ibanError($.mage.__('Bitte prüfen Sie Ihre IBAN.'));
                isValid = false;
            }

            if (!this.bic()) {
                this.bicError($.mage.__('Die BIC wird benötigt.'));
                isValid = false;
            }

            if (!this.bankName()) {
                this.bankNameError($.mage.__('Der Bankname wird benötigt.'));
                isValid = false;
            }

            return isValid;
        },

        resetFieldErrors: function () {
            this.accountHolderError('');
            this.ibanError('');
            this.bicError('');
            this.bankNameError('');
        },

        resetBankData: function () {
            this.bic('');
            this.bankName('');
        },

        scheduleIbanValidation: function (value) {
            var iban = this.cleanIban(value);
            clearTimeout(this.ibanValidationTimeout);

            if (!iban || iban.length < this.minIbanLength) {
                return;
            }

            this.ibanValidationTimeout = setTimeout(function () {
                this.validateIban();
            }.bind(this), this.ibanValidationDelay);
        },

        getConfigData: function () {
            return window.checkoutConfig.payment.diemayrei_sepa || {};
        },

        validateIban: function () {
            var self = this;
            var iban = this.cleanIban(this.iban());
            var config = this.getConfigData();
            var validationUrl = config.validationUrl || '';
            var formKey = config.formKey || window.checkoutConfig.formKey;

            clearTimeout(this.ibanValidationTimeout);

            this.iban(iban);
            this.ibanStatusMessage('');

            if (!iban) {
                this.ibanValidated(false);
                this.ibanError($.mage.__('Bitte geben Sie eine IBAN ein.'));
                return;
            }

            if (!validationUrl) {
                this.ibanValidated(false);
                this.ibanError($.mage.__('Die IBAN konnte nicht geprüft werden.'));
                return;
            }

            this.isIbanLoading(true);

            storage.post(
                validationUrl,
                JSON.stringify({
                    iban: iban,
                    form_key: formKey
                })
            ).done(function (response) {
                if (response && response.success && response.data && response.data.valid) {
                    var bankData = response.data.bankData || {};
                    self.ibanValidated(true);
                    self.ibanStatusMessage($.mage.__('IBAN erfolgreich geprüft.'));
                    if (bankData.bic) {
                        self.bic(bankData.bic);
                    }
                    if (bankData.name) {
                        self.bankName(bankData.name);
                    }
                    self.ibanError('');
                } else {
                    self.ibanValidated(false);
                    self.ibanStatusMessage('');
                    self.resetBankData();
                    self.ibanError($.mage.__('Die IBAN konnte nicht geprüft werden.'));
                }
            }).fail(function (response) {
                var message = $.mage.__('Die IBAN konnte nicht geprüft werden.');
                if (response && response.responseJSON && response.responseJSON.message) {
                    message = response.responseJSON.message;
                }
                self.ibanValidated(false);
                self.ibanStatusMessage('');
                self.resetBankData();
                self.ibanError(message);
            }).always(function () {
                self.isIbanLoading(false);
            });
        },

        cleanIban: function (value) {
            return (value || '').replace(/\s+/g, '').toUpperCase();
        }
    });
});
