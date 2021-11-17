/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
	'jquery',
	'uiComponent',
	'Fiserv_Payments/js/bluepay-shpf-utils',
	'Fiserv_Payments/js/adapter',
	'Magento_Ui/js/modal/alert',
	'Magento_Ui/js/lib/view/utils/dom-observer',
	'mage/translate'
], function (
	$, 
	Class, 
	shpfUtils, 
	pjsAdapter, 
	alert, 
	domObserver, 
	$t) {
	'use strict';

	return Class.extend({

		defaults: {
			code: 'fiserv_bluepay',
			gatewayConfig: null,
			pjsConfig: null,
			$selector: null,
			selector: 'edit_form',
			container: 'payment_form_fiserv_bluepay',
			active: false,
			scriptLoaded: false,
			bluepayOrigin: 'https://secure.bluepay.com',

			// selectors
			paymentTypeSelectorId: 'fiserv_bluepay-payment-type-selector',
			paymentCardFormContainerId: 'fiserv_bluepay-card-form-container',
			paymentCardFormId: 'fiserv_bluepay-form',
			iframeContainerId: 'fiserv_bluepay-ach-iframe-container',
			achIframeId: 'fiserv_bluepay-ach-iframe',
			paymentTokenInput: 'fiserv_bluepay_payment_token',
			paymentTypeInput: 'fiserv_bluepay_payment_type',
			paymentMethodName: '[name="payment[method]"',

			imports: {
				onActiveChange: 'active'
			}
		},

		initPayment: function (gatewayConfig, pjsConfig) {
			this.setGatewayConfig(gatewayConfig);
			this.setPjsConfig(pjsConfig);

			if (this.canAch()) {
				shpfUtils.setConfig(this.gatewayConfig.payment[this.code]);
			}

			if (this.canPaymentCard()) {
				// pjsAdapter requires:
				// 1. Gateway-specific config
				// 2. Callback for handling UI elements when payment flow begins
				// 3. Callback for handling payment flow success
				// 4. Callback for handlingn payment flow failure
				pjsAdapter.setPjsConfig(this.pjsConfig);
				pjsAdapter.setGatewayConfig(this.gatewayConfig.payment[this.code]);
				pjsAdapter.setBeginTokenFlowCallback(this.beginTokenFlow.bind(this));
				pjsAdapter.setTokenFlowSuccessCallback(this.tokenFlowSuccess.bind(this));
				pjsAdapter.setTokenFlowFailureCallback(this.tokenFlowFailure.bind(this));
			}
		},

		setGatewayConfig: function (gatewayConfig) {
			this.gatewayConfig = gatewayConfig;
		},

		setPjsConfig: function (pjsConfig) {
			this.pjsConfig = pjsConfig;
		},

		/**
		 * Set list of observable attributes
		 * @returns {exports.initObservable}
		 */
		initObservable: function () {
			var self = this;

			self.$selector = $('#' + self.selector);
			this._super()
				.observe([
					'active',
					'scriptLoaded',
					'selectedCardType'
				]);

			// re-init payment method events
			self.$selector.off('changePaymentMethod.' + this.code)
				.on('changePaymentMethod.' + this.code, this.changePaymentMethod.bind(this));

			// listen block changes
			domObserver.get('#' + self.container, function () {
				if (self.scriptLoaded()) {
					self.$selector.off('submit');
					self.initBlock();
					pjsAdapter.getApiClient(self.$selector);
				}
			});

			return this;
		},

		/**
		 * Performs tasks necessary for payment flow
		 */
		initBlock: function () {
			this.handlePaymentType();
			this.watchPaymentType();
		},

		/** 
		 * Deactivates card form when fiserv_bluepay not checked.
		 * isActive() not working with COD, for some reason.
		 */
		watchPaymentMethods: function () {
			let self = this;
			$(this.paymentMethodName).click(function() {
				let selected = $(this).attr('value');
				if (selected !== self.code && pjsAdapter.isActive()) {
					self.deactivateCardForm();
				}
			});
		},

		/**
		 * Enable/disable current payment method
		 * @param {Object} event
		 * @param {String} method
		 * @returns {exports.changePaymentMethod}
		 */
		changePaymentMethod: function (event, method) {
			this.active(method === this.code);

			return this;
		},

		/**
		 * Triggered when payment changed
		 *
		 * @param {Boolean} isActive
		 */
		onActiveChange: function (isActive) {
			if (!isActive) {
				this.$selector.off('submitOrder.' + this.code);
				return;
			}
			this.disableEventListeners();

			window.order.addExcludedPaymentMethod(this.code);

			this.enableEventListeners();
			this.initBlock();

			if (this.canPaymentCard() && !this.scriptLoaded()) {
				this.loadScript();
				this.watchPaymentMethods();
			}
		},

		/**
		 * Load external Payment.JS Library
		 */
		loadScript: function () {
			let state = this.scriptLoaded;
			let pjsEnv = this.mapPjsEnv(this.getEnvironment());
			pjsAdapter.createPjsForm(pjsEnv, this.$selector);
			state(true);
		},

		 /**
		 * Trigger order submit
		 */
		submitOrder: function () {
			if (this.getPaymentTokenInput().val()) {
				this.placeOrder('parent');
			} else if (this.isPaymentCardActive()) {
				this.beginTokenFlow();
				this.handleCardPayment();
			} else if (this.isAchActive()) {
				this.beginTokenFlow();
				this.handleAchPayment();
			}

			return false;
		},

		/* 
		* Can Payment Card be used in checkout?
		*/
		canPaymentCard: function () {
			return this.getPaymentTypeConfig().include('CC');
		},

		/* 
		* Can ACH be used in checkout?
		*/
		canAch: function () {
			return this.getPaymentTypeConfig().include('ACH');
		},

		/* 
		* Is ACH only available payment type?
		*/
		onlyAch: function () {
			return this.getPaymentTypeConfig() === 'ACH';
		},

		/* 
		* Is Payment Card only available payment type?
		*/
		onlyPaymentCard: function () {
			return this.getPaymentTypeConfig() === 'CC';
		},

		/* 
		* Is Payment card the active payment type?
		*/
		isPaymentCardActive: function () {
			return (
				!this.onlyAch() && (
					this.onlyPaymentCard() ||
					this.getSelectedPaymentType() === 'payment-card'
				)
			);
		},

		/* 
		* Is ACH the active payment type?
		*/
		isAchActive: function () {
			return (
				!this.onlyPaymentCard() && (
					this.onlyAch() ||
					this.getSelectedPaymentType() === 'ach'
				)
			);
		},

		multiplePaymentTypes: function () {
			return this.getPaymentTypeConfig() == "CCACH";
		},

		/* 
		* Create/Destroy appropriate forms for
		* selected payment type
		*/ 
		handlePaymentType: function () {
			if (this.isActive()) {
				if (this.isPaymentCardActive()) {
					this.activateCardForm();
					if (this.multiplePaymentTypes()) {
						this.deactivateAchIframe();
					}
				} else if (this.isAchActive()) {
					this.buildAchIframe();
					this.initAchIframe();
					if (this.multiplePaymentTypes()) {
						this.deactivateCardForm();
					}
				}				
			}
		},

		isActive: function () {
			return $('[value="' + this.code + '"]' + this.paymentMethodName).prop('checked');
		},

		/*
		* Hide and reset payment card form
		*/
		deactivateCardForm: function () {
			this.getCardFormContainer().hide();
			pjsAdapter.deactivate();

		},

		/*
		* Display payment card form
		*/
		activateCardForm: function () {
			this.getCardFormContainer().show();
			pjsAdapter.activate();
		},

		/*
		* Set iframe source if ACH is active
		*/
		initAchIframe: function () {
			if (this.isAchActive()) {
				shpfUtils.setBillingAddress(this.getBillingAddress());
				this.getAchIframe().attr('src', shpfUtils.getFullShpfUrl());
			}
		},

		/*
		* Display iframe
		*/
		buildAchIframe: function () {
			this.getIframeContainer().show();
		},

		/*
		* Hide iframe and clear source
		*/
		deactivateAchIframe: function () {
			this.getAchIframe().attr('src', "about:blank");
			this.getIframeContainer().hide();
		},


		handleCardPayment: function () {
			this.$selector.submit();
		},

		handleAchPayment: function () {
			// create promise and msg listener
			this.getIframePromise().then((token) => {
				this.tokenFlowSuccess({"token" : token});
			}).catch((err) => {
				this.tokenFlowFailure(err);
			});
			// submit shpf
			this.submitAchIframe();
		},

		getIframePromise: function () {
			return new Promise((resolve, reject) => {
				this.setIframeResponseListener(resolve, reject);
			});
		},

		setIframeResponseListener: function (successCb, failureCb) {
			let self = this;
			$(window).on('message', function(event) {
				let msg = event.originalEvent;
				if (msg.origin === self.bluepayOrigin) {
					let bpResponse = shpfUtils.parseBpResponse(msg.data);
					if (bpResponse.success === true) {
						successCb(bpResponse.token);
					} else {
						failureCb(bpResponse.message);
					}
				}
			});
		},

		submitAchIframe: function() {
			let submitMsg = this.getAchSubmitMsg();
			// find iframe
			let iframeCw = document.getElementById(this.achIframeId).contentWindow;
			// send submit message
			iframeCw.postMessage(submitMsg, '*');
		},

		getAchSubmitMsg: function () {
			return { 
				'originCode' : 'fiserv_bluepay_magento',
				'action' : 'submit'
			};
		},

		beginTokenFlow: function () {
			// $('body').trigger('processStart');
		},

		/**
		 * Completes a successful token flow
		 * and places order
		 *
		 * @param {Array} response
		 */
		tokenFlowSuccess: function (response) {
			let brand = response["brand"];
			$('body').trigger('processStop');
			this.setPaymentTokenInfo(response["token"]);
			this.setPaymentTypeInfo();
			this.placeOrder('parent');
		},

		/**
		 * Completes a failed token flow
		 *
		 * @param {Object} error
		 */
		tokenFlowFailure: function (error) {
			$('body').trigger('processStop');
			alert({
				content: error
			});
		},

        /**
         * Place order
         */
        placeOrder: function (key) {
        	if (key) {
	            $('#' + this.selector).trigger('realOrder');
        	}
        },

		/**
		 * Sets value of hidden Payment Token input
		 *
		 * @param {string} token
		 */
		setPaymentTokenInfo: function (token) {
			this.getPaymentTokenInput().val(token);
		},

		/**
		 * Sets value of hidden Payment Type input
		 */
		setPaymentTypeInfo: function () {
			let paymentType = this.isPaymentCardActive() ? "CREDIT" : "ACH";
			this.getPaymentTypeInput().val(paymentType);
		},

		///////////////
		// Retrieval // 
		///////////////

		/**
		 * Map BluePay Gateway environment to Payment.JS environment
		 * TEST => uat
		 * LIVE => prod
		 *
		 * @returns {String}
		 */
		mapPjsEnv: function (bpEnv) {
			let env = '';
			if (bpEnv === 'TEST') {
				env = 'uat';
			} else if (bpEnv === 'LIVE') {
				env = 'prod';
			}
			return env;
		},

		/**
		 * Get BluePay Gateway Environment
		 *
		 * @returns {String}
		 */
		getEnvironment: function () {
			return this.gatewayConfig.payment[this.code].environment;
		},

		/* 
		* Retrieve configured payment type
		*/ 
		getPaymentTypeConfig: function () {
			return this.gatewayConfig.payment[this.code]['paymentType'];
		},

		/* 
		* Retrieve payment type selector
		*/ 
		getPaymentTypeSelector: function () {
			return $('#' + this.paymentTypeSelectorId);
		},

		/* 
		* Retrieve value of payment type selector
		*/ 
		getSelectedPaymentType: function () {
			return this.getPaymentTypeSelector().val();
		},

		/*
		* Retrieve payment card form container
		*/
		getCardFormContainer: function () {
			return $('#' + this.paymentCardFormContainerId);
		},

		/*
		* Retrieve payment card form
		*/
		getCardForm: function () {
			return $('#' + this.paymentCardFormId);
		},
		
		/* 
		* Retrieve BluePay ACH Iframe
		*/     
		getAchIframe: function () {
			return $('#' + this.achIframeId);
		},

		/*
		* Retrieve iframe container
		*/
		getIframeContainer: function () {
			return $('#' + this.iframeContainerId);
		},

		/**
		 * Retrieve hidden payment token input
		 */
		getPaymentTokenInput: function () {
			return $('#' + this.paymentTokenInput);
		},

		/**
		 * Retrieve hidden payment type input
		 */
		getPaymentTypeInput: function () {
			return $('#' + this.paymentTypeInput);
		},

		getBillingAddress: function () {
			let _region = $('#order-billing_address_region_id option:selected').html() !== "Please select" ? $('#order-billing_address_region_id option:selected').html() : '';
			return {
				firstname: $('#order-billing_address_firstname').val(),
				lastname: $('#order-billing_address_lastname').val(),
				company: $('#order-billing_address_company').val(),
				street: [
					$('#order-billing_address_street0').val(),
					$('#order-billing_address_street1').val()
				],
				city: $('#order-billing_address_city').val(),
				region: _region,
				postcode: $('#order-billing_address_postcode').val(),
				country: $('#order-billing_address_country_id').val()
			};

		},

		watchPaymentType: function () {
			if (this.multiplePaymentTypes()) {
				this.getPaymentTypeSelector().on('change', () => {
					this.handlePaymentType();
				});
			}
		},

		/**
		 * Enable form event listeners
		 */
		enableEventListeners: function () {
			this.$selector.on('submitOrder.' + this.code, this.submitOrder.bind(this));
		},

		/**
		 * Disable form event listeners
		 */
		disableEventListeners: function () {
			this.$selector.off('submitOrder');
			this.$selector.off('submit');
		}

	});
});
