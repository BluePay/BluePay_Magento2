<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\Bluepay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Gets config values using field names
	const KEY_ACTIVE = 'active';
	const KEY_ACCOUNT_ID = 'account_id';
	const KEY_SECRET_KEY = 'secret_key';
	const KEY_ENVIRONMENT = 'trans_mode';
	const KEY_PAYMENT_TYPE = 'payment_type';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_CC_TYPES = 'cctypes';
	const KEY_USE_CCV = 'useccv';
	const KEY_CURRENCY = 'currency';
	const KEY_CC_TYPES_MAPPER ='cc_types_bluepay_mapper';
	const KEY_API_URL = 'api_endpoint';
	const KEY_ACH_SHPF_URL = 'ach_shpf_url';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	/**
	 * Fiserv Bluepay config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
		$methodCode = null,
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
			->get(Json::class);
	}

	/**
	 * Gets Payment configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Returns Bluepay account id.
	 *
	 * @return string
	 */
	public function getAccountId($storeId = null)
	{
		return $this->getValue(self::KEY_ACCOUNT_ID, $storeId);
	}

	/**
	 * Returns Bluepay secret key.
	 *
	 * @return string
	 */
	public function getSecretKey($storeId = null)
	{
		return $this->getValue(self::KEY_SECRET_KEY, $storeId);
	}

	/**
	 * Gets value of BluePay transaction mode.
	 *
	 * Possible values: live or test.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getEnvironment($storeId = null)
	{
		return $this->getValue(self::KEY_ENVIRONMENT, $storeId);
	}

	/**
	 * Gets value of BluePay payment type.
	 *
	 * Possible values: CCACH, CC, or ACH.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentType($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_TYPE, $storeId);
	}

	/**
	 * Gets value of BluePay payment action.
	 *
	 * Possible values: Sale or Authorize Only.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}


	/**
	 * Retrieve available credit card types
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getAvailableCardTypes($storeId = null)
	{
		$ccTypes = $this->getValue(self::KEY_CC_TYPES, $storeId);

		return !empty($ccTypes) ? explode(',', $ccTypes) : [];
	}

	/**
	 * Checks if ccv field is enabled.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isCcvEnabled($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_USE_CCV, $storeId);
	}

	/**
	 * Gets value of configured currency.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getCurrency($storeId = null)
	{
		return $this->getValue(self::KEY_CURRENCY, $storeId);
	}

	/**
	 * Returns BluePay BP10emu API URL.
	 *
	 * @return string
	 */
	public function getBpApiUrl()
	{
		return $this->getValue(self::KEY_API_URL);
	}

	/**
	 * Retrieve mapper between Magento and BluePay card types
	 *
	 * @return array
	 */
	public function getCcTypesMapper()
	{
		$result = json_decode(
			$this->getValue(self::KEY_CC_TYPES_MAPPER),
			true
		);

		return is_array($result) ? $result : [];
	}

	/**
	 * Returns URL of the BluePay SHPF
	 * used for $0 ACH Authorization transactions
	 *
	 * @return string
	 */
	public function getAchShpfUrl()
	{
		return $this->getValue(self::KEY_ACH_SHPF_URL);
	}

}
