/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
	[
		'underscore',
		'jquery',
		'Magento_Payment/js/view/payment/cc-form',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Fiserv_Payments/js/validator',
		'Fiserv_Payments/js/bluepay-shpf-utils',
		'Fiserv_Payments/js/adapter',
		'Magento_Ui/js/model/messageList',
		'Fiserv_Payments/js/view/payment/validator-handler',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Checkout/js/model/full-screen-loader',
		'mage/translate',
		'prototype',
		'domReady!'
	],
	function (
		_,
		$,
		Component,
		quote,
		checkoutData,
		validator,
		shpfUtils,
		pjsAdapter,
		globalMessageList,
		validatorManager,
		VaultEnabler,
		fullScreenLoader,
		$t
	) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Fiserv_Payments/payment/bluepay/form',
				active: false,
				code: 'fiserv_bluepay',
				fiserv_code: 'fiserv_payments',
				paymentPayload: {
					token: null,
					type: null
				},
				additionalData: {},
				paymentTypeSelectorId: 'fiserv_bluepay-payment-type-selector',
				paymentCardFormContainerId: 'fiserv_bluepay-card-form-container',
				paymentCardFormId: 'fiserv_bluepay-form',
				iframeContainerId: 'fiserv_bluepay-ach-iframe-container',
				paymentMethodName: '[name="payment[method]"',
				achIframeId: 'fiserv_bluepay-ach-iframe',
				bluepayOrigin: 'https://secure.bluepay.com'
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize: function () {
				var self = this;

				self._super();
				self.vaultEnabler = new VaultEnabler();
				self.vaultEnabler.setPaymentCode(self.getVaultCode());

				return self;
			},

			/**
			 * Set list of observable attributes
			 *
			 * @returns {exports.initObservable}
			 */
			initObservable: function () {

				if (this.canAch()) {
					shpfUtils.setConfig(window.checkoutConfig.payment[this.getCode()]);
					shpfUtils.setBillingAddress(this.getBillingAddress());
					quote.billingAddress.subscribe(
						this.refreshBillingAddress.bind(this)
					);
				}

				if (this.canPaymentCard()) {
					this.initPjsAdapter();
				}

				validator.setConfig(window.checkoutConfig.payment[this.getCode()]);

				this._super()
					.observe(['active']);

				return this;
			},

			refreshBillingAddress: function () {
				if (this.isAchActive() && quote.billingAddress()) {
					shpfUtils.setBillingAddress(quote.billingAddress());
					this.initAchIframe();
				}
			},

			initPjsAdapter: function () {
					// pjsAdapter requires:
					// 1. Gateway-specific config
					// 2. Callback for handling UI elements when payment flow begins
					// 3. Callback for handling payment flow success
					// 4. Callback for handlingn payment flow failure
					pjsAdapter.initialize();
					pjsAdapter.setGatewayConfig(window.checkoutConfig.payment[this.getCode()]);
					pjsAdapter.setBeginTokenFlowCallback(this.beginTokenFlow.bind(this));
					pjsAdapter.setTokenFlowSuccessCallback(this.cardTokenFlowSuccess.bind(this));
					pjsAdapter.setTokenFlowFailureCallback(this.tokenFlowFailure.bind(this));
			},

			/**
			 * Get payment name
			 *
			 * @returns {String}
			 */
			getCode: function () {
				return this.code;
			},

			/**
			 * @returns {Boolean}
			 */
			isVaultEnabled: function () {
				return this.vaultEnabler.isVaultEnabled();
			},

			/**
			 * Returns vault code.
			 *
			 * @returns {String}
			 */
			getVaultCode: function () {
				return window.checkoutConfig.payment[this.getCode()].vaultCode;
			},

			/** 
			 * Deactivates card form when fiserv_bluepay not checked.
			 * isActive() not working with COD, for some reason.
			 */
			watchPaymentMethods: function () {
				let self = this;
				$(this.paymentMethodName).click(function() {
					let selected = $(this).attr('id');
					if (selected === self.getCode()) {
						self.activateCardForm();
					} else if (pjsAdapter.isActive()) {
						self.deactivateCardForm();
					}
				});
			},

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
				return window.checkoutConfig.payment[this.getCode()].environment;
			},

			/**
			* Create pjs script tag with Payment.JS client source 
			* retreived using gateway environment value
			* 
			*/
			createCardForm: function () {
				let pjsEnv = this.mapPjsEnv(this.getEnvironment());
				pjsAdapter.createPjsForm(pjsEnv, this.getCardForm());
			},

			/**
			 * Get billing address
			 *
			 * @returns {String}
			 */
			getBillingAddress: function () {
				let billingAddress = checkoutData.getBillingAddressFromData();
				if (!billingAddress) {
					billingAddress = quote.billingAddress();
				}
				return billingAddress;
			},

			/**
			 * Check if payment is active
			 *
			 * @returns {Boolean}
			 */
			isActive: function () {
				let active = this.getCode() === this.isChecked();

				this.active(active);

				return active;
			},

			changePaymentType: function (val) {
				this.handlePaymentType();
			},

			getPaymentTypeConfig: function () {
				return window.checkoutConfig.payment[this.getCode()]['paymentType'];
			},

			multiplePaymentTypes: function () {
				return this.getPaymentTypeConfig() == "CCACH";
			},

			/* 
			* Create/Destroy appropriate forms for
			* selected payment type
			*/ 
			handlePaymentType: function () {
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
			},

			/* 
			* Retreive payment type selector
			*/ 
			getPaymentTypeSelector: function () {
				return $('#' + this.paymentTypeSelectorId);
			},

			/* 
			* Retreive value of payment type selector
			*/ 
			getSelectedPaymentType: function () {
				return this.getPaymentTypeSelector().val();
			},

			/* 
			* Can ACH be used in checkout?
			*/
			canAch: function () {
				return this.getPaymentTypeConfig().include('ACH');
			},

			/* 
			* Can Payment Card be used in checkout?
			*/
			canPaymentCard: function () {
				return this.getPaymentTypeConfig().include('CC');
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

			/*
			* Set iframe source if ACH is active
			*/
			initAchIframe: function () {
				if (this.isAchActive()) {
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

			getAchIframe: function () {
				return $('#' + this.achIframeId);
			},

			/*
			* Retreive iframe container
			*/
			getIframeContainer: function () {
				return $('#' + this.iframeContainerId);
			},

			/*
			* Hide and reset payment card form
			*/
			deactivateCardForm: function () {
				this.getCardFormContainer().hide();
				this.getCardForm().trigger('reset');
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
			* Retreive payment card form container
			*/
			getCardFormContainer: function () {
				return $('#' + this.paymentCardFormContainerId);
			},

			/*
			* Retreive payment card form
			*/
			getCardForm: function () {
				return $('#' + this.paymentCardFormId);
			},

			/**
			 * Get data
			 *
			 * @returns {Object}
			 */
			getData: function () {
				var data = {
					'method': this.getCode(),
					'additional_data': {
						'payment_token': this.paymentPayload.token,
						'payment_type': this.paymentPayload.type
					}
				};

				data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
				this.vaultEnabler.visitAdditionalData(data);

				return data;
			},

			/**
			 * Get list of available CC types
			 *
			 * @returns {Object}
			 */
			getCcAvailableTypes: function () {  
				return validator.getAvailableCardTypes();
			},

			/**
			 * Action to place order
			 */
			placeOrder: function (key) {
				var self = this;

				if (key) {
					return self._super();
				}
				// place order on success validation
				validatorManager.validate(self, function () {
					return self.placeOrder('parent');
				}, function (err) {

					if (err) {
						self.showError(err);
					}
				});

				return false;
			},

			/**
			 * Trigger order placing
			 */
			placeOrderClick: function () {
				if (this.paymentPayload.token) {
					this.placeOrder('parent');
				} else if (this.isPaymentCardActive()) {
					this.beginTokenFlow();
					this.handleCardPayment();
				} else if (this.isAchActive()) {
					this.beginTokenFlow();
					this.handleAchPayment();
				}
			},

			handleCardPayment: function () {
				this.getCardForm().submit();
			},

			handleAchPayment: function () {
				// create promise and msg listener
				this.getIframePromise().then((token) => {
					this.tokenFlowSuccess(token);
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

			setPaymentTokenInfo: function (token) {
				this.setPaymentPayload(token);
			},

			getAchSubmitMsg: function () {
				return { 
					'originCode' : 'fiserv_bluepay_magento',
					'action' : 'submit'
				};
			},

			getPjsFieldContainerStyles: function () {
				return { 
					"margin-bottom": "25px"
				 };
			},

			getPjsFlexRowStyle: function () {
				return {
					"display": "flex",
					"justify-content": "space-between",
					"width": "220px",
					"margin-bottom": "25px"
				};
			},

			getPjsFlexFieldContainerStyles: function () {
				return { 
					"width": "100px"
				 };
			},

			getPjsFlexFieldStyle: function () {
				return {
					"height": "32px",
					"margin-top": "8px",
					"border": "1px solid #ccc",
					"padding": "0 9px"
				};
			},

			getPjsFieldStyles: function () {
				return {
					"height": "32px",
					"margin-top": "8px",
					"border": "1px solid #ccc",
					"width": "200px",
					"padding": "0 9px"
				};
			},

			beginTokenFlow: function () {
				fullScreenLoader.startLoader();
			},

			tokenFlowSuccess: function (paymentToken) {
				fullScreenLoader.stopLoader();
				this.setPaymentTokenInfo(paymentToken);
				this.placeOrder('parent');
			},

			/**
			 * Completes a successful token flow
			 * and places order
			 *
		 	 * @param {Array} response
			 */
			cardTokenFlowSuccess: function (response) {
				let brand = response["brand"];
				if (this.validateCardType(brand)) {
					this.tokenFlowSuccess(response["token"]);
				} else {
					pjsAdapter.pjsFailure("Unsupported payment card type");
				}
			},

			validateCardType: function (brand) {
				let result = false;
				let mapper = pjsAdapter.getCcTypesMapper();
				if (Object.keys(mapper).length) {
					let ccType = mapper[brand.toUpperCase()];
					if (ccType) {
						if (this.getCcAvailableTypes().include(ccType)) {
							result = true;
						}
					}
				}
				return result;
			},

			/**
			 * Completes a failed token flow
			 *
			 * @param {Object} error
			 */
			tokenFlowFailure: function (error) {
				fullScreenLoader.stopLoader();
				this.showError(error);
				alert(error);
			},


			/**
			 * Sets payment token and type information
			 *
			 * @param {Object} paymentToken
			 * @private
			 */	
			setPaymentPayload: function (paymentToken) {
				this.paymentPayload.token = paymentToken;
				this.paymentPayload.type = this.isAchActive() ? 'ACH' :'CREDIT';
			},

			/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
			showError: function (errorMessage) {
				globalMessageList.addErrorMessage({
					message: errorMessage
				});
			}
		});
	}
);
