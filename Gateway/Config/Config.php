<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{

	const PATH_PATTERN = '%s/%s';

	// Gets config values using field names
	const KEY_ACTIVE = 'active';
	const KEY_PJS_KEY = 'pjs_key';
	const KEY_PJS_SECRET = 'pjs_secret';
	const KEY_SANDBOX_LIB_URL = 'pjs_uat_client_url';
	const KEY_PROD_LIB_URL = 'pjs_prod_client_url';
	const KEY_PROD_SERVICE_URL = 'pjs_prod_service_url';
	const KEY_SANDBOX_SERVICE_URL = 'pjs_sandbox_service_url';
	const KEY_CC_TYPES_MAPPER ='cc_types_pjs_mapper';
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
		$pathPattern = self::PATH_PATTERN,
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
	 * Returns payment.js key.
	 *
	 * @return string
	 */
	public function getPjsKey()
	{
		return $this->getValue(self::KEY_PJS_KEY);
	}

	/**
	 * Returns payment.js secret.
	 *
	 * @return string
	 */
	public function getPjsSecret()
	{
		return $this->getValue(self::KEY_PJS_SECRET);
	}

	/**
	 * Returns sandbox SDK url.
	 *
	 * @return string
	 */
	public function getPjsUatLibUrl()
	{
		return $this->getValue(self::KEY_SANDBOX_LIB_URL);
	}

	/**
	 * Returns productino SDK url.
	 *
	 * @return string
	 */
	public function getPjsProdLibUrl()
	{
		return $this->getValue(self::KEY_PROD_LIB_URL);
	}

	/**
	 * Returns Payment.JS production service URL.
	 *
	 * @return string
	 */
	public function getPjsProdUrl()
	{
		return $this->getValue(self::KEY_PROD_SERVICE_URL);
	}

	/**
	 * Returns Payment.JS sandbox service URL.
	 *
	 * @return string
	 */
	public function getPjsSandboxUrl()
	{
		return $this->getValue(self::KEY_SANDBOX_SERVICE_URL);
	}

	/**
	 * Retrieve mapper between Magento and PJS card types
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

}
