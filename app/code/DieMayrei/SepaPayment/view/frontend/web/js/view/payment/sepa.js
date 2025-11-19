define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'diemayrei_sepa',
        component: 'DieMayrei_SepaPayment/js/view/payment/method-renderer/sepa'
    });

    return Component.extend({});
});
