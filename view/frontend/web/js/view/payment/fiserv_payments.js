define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        let config = window.checkoutConfig.payment;
        let bluepay = 'fiserv_bluepay';
        
        if (config[bluepay].isActive) {
            rendererList.push(
                {
                    type: bluepay,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/bluepay-form'
                }
            );
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
