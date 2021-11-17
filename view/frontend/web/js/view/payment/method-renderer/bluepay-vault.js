define([
    'jquery',
	'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, VaultComponent, globalMessageList, fullScreenLoader) {
	'use strict';

	return VaultComponent.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/bluepay/vault-form',
			bluepayCode: 'fiserv_bluepay',
			additionalData: {}
		},

		/**
		 * Determines if stored payment account should
		 * be displayed based on active payment types
		 * @returns {Bool}
		 */
		 canUse: function () {
	 		if (this.isCardToken()) {
	 			return this.canPaymentCard();
	 		}
	 		if (this.isAchToken()) {
	 			return this.canAch();
	 		}
		 },

		 /**
		 * Returns string representation of payment token type
		 * @returns {String}
		 */
		 getPaymentTokenType: function () {
	 		if (this.isCardToken()) {
	 			return "CREDIT";
	 		}
	 		if (this.isAchToken()) {
	 			return "ACH";
	 		}
		 },

		/**
		 * Returns configured Bluepay Payment Types
 		 * @returns {String}
		 */
		 getPaymentTypeConfig: function () {
		 	return window.checkoutConfig.payment[this.bluepayCode]['paymentType'];
		 },

	 	/** 
		 * Can ACH be used in checkout?
		 */
		canAch: function () {
			return this.getPaymentTypeConfig().include('ACH');
		},

		/** 
		 * Can Payment Card be used in checkout?
		 */
		canPaymentCard: function () {
			return this.getPaymentTypeConfig().include('CC');
		},

		/**
		 * Determines if token is payment card
		 * @returns {Bool}
		 */
		isCardToken: function () {
			return this.componentType === 'card';
		},

		getIconUrl: function (cardType) {
			let url = this.getIcons(cardType).url;
			if (typeof(url) === "object") {
				url = url.first();
			}
			return url;
		},

		/**
		 * Determines if token is payment card
		 * @returns {Bool}
		 */
		isAchToken: function () {
			return this.componentType === 'ach';
		},

	   /**
		 * Get last 4 digits of card
		 * @returns {String}
		 */
		getMaskedCard: function () {
			return this.details.maskedCC.toLowerCase();
		},

		/**
		 * Get expiration date
		 * @returns {String}
		 */
		getExpirationDate: function () {
			return this.details.expirationDate;
		},

		/**
		 * Get card type
		 * @returns {String}
		 */
		getCardType: function () {
			return this.details.type;
		},

		/**
		 * Get ACH account type
		 * @returns {String}
		 */
		getAchAccountType: function () {
			let accountType = this.details['account_type'];
			return accountType[0].toUpperCase() + accountType.substring(1);
		},

		/**
		 * Get ACH Routing Number
		 * @returns {String}
		 */
		getRoutingNumber: function () {
			return this.details['routing_number'];
		},

		/**
		 * Get masked ACH account number
		 * @returns {String}
		 */
		getAchBin: function () {
			return this.details['ach_bin'];
		},

		/**
		 * Get masked ACH accountt number 
		 * and routing number
		 * @returns {String}
		 */
		getMaskedAch: function () {
			return this.getRoutingNumber() + ":" + this.getAchBin();
		},

		getAchAccountString: function () {
			return this.getAchAccountType() + ": " + this.getRoutingNumber() + ":xxxx" + this.getAchBin();
		},

		/**
		 * Place order
		 */
		placeOrderClick: function () {
			this.getPaymentMethodToken();
		},

		/**
		 * Send request to get payment method token
		 */
		getPaymentMethodToken: function () {
			var self = this;
			fullScreenLoader.startLoader();
			$.getJSON(self.tokenUrl, {
				'public_hash': self.publicHash
			})
				.done(function (response) {
					fullScreenLoader.stopLoader();
					self.additionalData['payment_token'] = response.paymentToken;
					self.placeOrder();
				})
				.fail(function (response) {
					var error = JSON.parse(response.responseText);

					fullScreenLoader.stopLoader();
					globalMessageList.addErrorMessage({
						message: error.message
					});
				});
		},

		/**
		 * Get payment method data
		 * @returns {Object}
		 */
		getData: function () {
			var data = {
				'method': this.code,
				'additional_data': {
					'public_hash': this.publicHash,
					'payment_type': this.getPaymentTokenType()
				}
			};

			data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

			return data;
		}

	});
});