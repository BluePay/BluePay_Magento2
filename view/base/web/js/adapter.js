/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
	'jquery',
], function (
	$, 
) {
	'use strict';

	// pjsAdapter requires:
	// 1. Gateway-specific config
	// 2. Callback for handling UI elements when payment flow begins
	// 3. Callback for handling payment flow success
	// 4. Callback for handlingn payment flow failure
	return {
		gatewayConfig: {},
		config: {},
		active: false,
		code: 'fiserv_payments',
		publicKeyKey: 'publicKeyBase64',
		clientTokenKey: 'clientToken',
		pjsAuthDataKey: 'pjsAuthData',
		tokenDataUrl: 'fiserv/tokendata/retrieve',
		clientTokenParam: 'Client-Token',
		storeUrlKey: 'storeUrl',
		ccMapperKey: 'ccTypesMapper',
		availableCcTypesKey: 'availableCardTypes',
		// Payment JS Form:
		paymentForm: null,
		// token flow callbacks:
		beginTokenFlowCallback: null,
		tokenFlowSuccessCallback: null,
		tokenFlowFailureCallback: null,


		/**
		 * @returns {exports.initialize}
		 */
		initialize: function () {
			this.setConfig();
		},

		/**
		 * marks Payment.JS adapter as active
		 */
		activate: function () {
			this.active = true;
		},

		/**
		 * marks Payment.JS adapter as inactive
		 */
		deactivate: function () {
			this.active = false;
		},

		/**
		 * Retrieves Payment.JS adapter state
		 */
		isActive: function () {
			return this.active;
		},

		/**
		 * Set gateway configuration
		 * @param {Object} config
		 */
		setGatewayConfig: function (config) {
			this.gatewayConfig = config;
			this.setAuthData();
		},

		/**
		 * Set Payment.JS configuration
		 * from external param
		 * @param {Object} config
		 */
		setPjsConfig: function (config) {
			this.config = config.payment[this.code];
		},

		/**
		 * Set configuration
		 */
		setConfig: function () {
			this.config = window.checkoutConfig.payment[this.code];
		},

		setBeginTokenFlowCallback: function (callback) {
			this.beginTokenFlowCallback = callback;
		},

		setTokenFlowSuccessCallback: function (callback) {
			this.tokenFlowSuccessCallback = callback;
		},

		setTokenFlowFailureCallback: function (callback) {
			this.tokenFlowFailureCallback = callback;
		}, 

		/**
		 * Returns Payment.JS API client
		 *
		 */
		getApiClient: function (form) {
			let self = this;
			let hooks = this.getHooks();
			let config = this.getPjsConfig();
			let successCb = this.retrievePaymentToken.bind(this);
			let failureCb = this.pjsFailure.bind(this);

			window.firstdata.createPaymentForm(
				config,
				hooks,
				(paymentForm) => {
					self.paymentForm = paymentForm;
					form.one('submit', (e) => {
						if (self.isActive()) {
							e.preventDefault();
							paymentForm.onSubmit(
								successCb,
								failureCb
							);							
						}
					});
				}
			);
		},

		/**  
		 * Handle Payment.JS form in the event of a tokenization failure.
		 * Triggers configured failure callback
		 *
		 */
		getPaymentForm: function () {
			return this.paymentForm;
		},

		/**  
		 * Handle Payment.JS form in the event of a tokenization failure.
		 * Triggers configured failure callback
		 *
		 */
		pjsFailure: function (failure) {
			this.paymentForm.reset(() => console.log(failure));
			this.tokenFlowFailureCallback(failure);
		},

		/**
		 * Retreives Payment.JS client url
		 * based on configured environment
		 * 
		 */
		getPjsClientSrc: function (env) {
			let _src = '';
			if (env === 'prod') {
				_src = this.config.pjsProdLibUrl;
			} else if (env === 'uat') {
				_src = this.config.pjsUatLibUrl;
			}

			return _src;
		},

		/**
		 * Instantiates Payment.JS form
		 * based on Gatway environment
		 * 
		 */
		createPjsForm: function (env, form) {
			let pjsSrc = this.getPjsClientSrc(env);
			let pjsScript = document.createElement('script');
			pjsScript.onload = () => {
				this.getApiClient(form);
			};
			pjsScript.src = pjsSrc;
			document.head.appendChild(pjsScript);
		},

		/**
		 * Get Payment JS card types mapped to Magento card types
		 *
		 * @returns {Array}
		 */
		getCcTypesMapper: function () {
			return this.config[this.ccMapperKey];
		},

		/**
		 * Attempts to retreive token 
		 * a maximum of 20 times
		 */
		retrievePaymentToken: function (clientToken, tries = 20) {
			let tokenQuery = new Promise((resolve, reject) => {
				this.queryTokenData(clientToken, resolve, reject);
			}).then((response) => {
				if (response["token"]) {
					this.tokenFlowSuccessCallback(response);
				} else {
					let errorMsg = response["error"] ?? "An error occurred while tokenizing payment info.";
					this.pjsFailure(errorMsg);
				}
			}).catch((e) => {
				console.log(e);
				tries--;
				if (tries === 0) {
					this.pjsFailure("Unable to locate payment token data.");
				} else {
					setTimeout(() => {
						this.retrievePaymentToken(clientToken, tries);
					}, 1000);
				}
			});
		},

		/**
		 * Makes ajax request to retreive token
		 */
		queryTokenData: function (clientToken, successCb, failureCb) {
			let _resp = false;
			let self = this;
			let _data = { };
			_data[self.clientTokenParam] = clientToken;
			$.ajax({
				url: self.config[self.storeUrlKey] + self.tokenDataUrl,
				data: _data,
				cache: false,
				dataType: 'json',
				type: "GET",
				success: function(response) {
					successCb(response);
				},
				error: function(err) {
					failureCb(err);
				}
			});
		},

		/**
		 *  Extract Payment.JS Auth Data
		 */
		setAuthData: function () {
			this.authData = JSON.parse(this.gatewayConfig[this.pjsAuthDataKey]);
		},

		/**
		 * Facade to pass auth data
		 * to Payment.JS callback function
		 *
		 * @returns {Array}
		 */
		passAuthData: function (callback) {
			let _data = this.authData;
			if (_data && 
				_data[this.publicKeyKey] &&
				_data[this.clientTokenKey]) {
				callback(_data);
			} else {
				throw new Error("Payment.JS authorization data not found.");
			}
		},

		/**
		 * Hooks for Payment.JS Form instantiation
		 *
		 * @returns {Array}
		 */
		getHooks: function () {
			return { preFlowHook: this.passAuthData.bind(this) };
		},

		/**
		 * Returns avaialble card brands
		 * mapped from Magento types to PJS types
		 *
		 * @returns {Array}
		 */
		getAvailbleCardTypes: function () {
			let mapper = this.getCcTypesMapper();
			let pjsCardTypes = Object.keys(mapper);
			let magentoCcTypes = this.gatewayConfig[this.availableCcTypesKey];
			let availableTypes = [];
			for (var i = magentoCcTypes.length - 1; i >= 0; i--) {
				for (var j = pjsCardTypes.length - 1; j >= 0; j--) {
					if (mapper[pjsCardTypes[j]] === magentoCcTypes[i]) {
						availableTypes.push(pjsCardTypes[j].toLowerCase());
					}
				}
			}
			return availableTypes;
		},

		/**
		 * Configuration information for
		 * Payment.JS fields
		 *
		 * @returns {Array}
		 */
		getPjsConfig: function () {
			return {
				styles: {
					input: {
						'font-size': '14px',
						"line-height": "1.42857143",
						color: '#333',
						'font-family': "'Open Sans','Helvetica Neue',Helvetica,Arial,sans-serif;'font-family': 'Verdana,Arial,sans-serif'",
						background: '#F9F9F9',
					},

					".invalidClass": {
						color: "#C01324",
					},
					".validClass": {
						color: "#43B02A",
					},
				},

				classes: {
					empty: "emptyClass",
					focus: "focusClass",
					invalid: "invalidClass",
					valid: "validClass",
				},

				fields: {
					card: {
						selector: '[data-cc-card]',
						placeholder: 'Credit Card Number',
						// optional, defaults to all brands being allowed.
						// see section titled "Restrict Card Brands" below for more information
						allowedBrands: this.getAvailbleCardTypes()
					},
					cvv: {
						selector: '[data-cc-cvv]',
						placeholder: 'CVV',
					},
					exp: {
						selector: '[data-cc-exp]',
						placeholder: 'Expiration Date',
					},
					name: {
						selector: '[data-cc-name]',
						placeholder: 'Cardholder Name',
					},
				}
			};
		}

	};
});
