/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
	'underscore'
], function (_) {
	'use strict';

	return {
		config: {},
		m2BillingAddress: {},

		/**
		 * Set configuration
		 * @param {Object} config
		 */
		setConfig: function (config) {
			this.config = config;
		},

		/**
		 * Set billing address
		 * @param {Object} address
		 */
		setBillingAddress: function (address) {
			this.m2BillingAddress = address;
		},

		/**
		 * Get shpf query string
		 * @returns {string}
		 */
		getShpfQueryString: function () {
			let query = {
				'NAME1' : this.m2BillingAddress.firstname ? this.m2BillingAddress.firstname : '',
				'NAME2' : this.m2BillingAddress.lastname  ? this.m2BillingAddress.lastname : '',
				'COMPANY_NAME' : this.m2BillingAddress.company ? this.m2BillingAddress.company : '',
				'ADDR1' : this.m2BillingAddress.street && typeof(this.m2BillingAddress.street[0]) != "undefined" ? this.m2BillingAddress.street[0] : '',
				'ADDR2' : this.m2BillingAddress.street && typeof(this.m2BillingAddress.street[1]) != "undefined" ? this.m2BillingAddress.street[1] : '',
				'CITY' : this.m2BillingAddress.city ? this.m2BillingAddress.city : '',
				'STATE' : this.m2BillingAddress.region ? this.m2BillingAddress.region : '',
				'COUNTRY' : this.m2BillingAddress.country ? this.m2BillingAddress.country : '',
				'ZIPCODE' : this.m2BillingAddress.postcode ? this.m2BillingAddress.postcode : '',
				'MERCHANT' : this.config.accountId,
				'TPS_DEF' : this.config.tps_def,
				'TPS_HASH_TYPE' : this.config.tps_hash_type,
				'TAMPER_PROOF_SEAL' : this.config.tps,
				'MODE' : this.config.environment
			}

			return this.encodeQuery(query);
		},

		getUrlFields: function (baseUrl) {
			let query = {
				'APPROVED_URL' : baseUrl,
				'DECLINED_URL' : baseUrl,
				'MISSING_URL' : baseUrl 
			}

			return this.encodeQuery(query);
		},

		encodeQuery: function (query) {
			let esc = encodeURIComponent;
			let queryString = Object.keys(query)
				.map(k => esc(k) + '=' + esc(query[k]))
				.join('&');

			return queryString;

		},

		getFullShpfUrl: function () {
			let baseUrl = this.config.ach_shpf_url + "&" + this.getShpfQueryString();
			return baseUrl + "&" + this.getUrlFields(baseUrl);
		},

		parseBpResponse: function (response) {
			let parsedResponse = {};
			parsedResponse.success = false;
			parsedResponse.message = response;
			if (typeof(response) !== 'string') {
				if (response['Result'] && response['Result'].toUpperCase() === 'APPROVED') {
					parsedResponse.success = true;
					parsedResponse.token = response['RRNO'];
				} else {
					parsedResponse.message = response['MESSAGE'];
				}
			} 
			return parsedResponse;
		}
	};
});
