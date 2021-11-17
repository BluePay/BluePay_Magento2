<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\Bluepay;

use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Fiserv\Payments\Model\Adapter\Bluepay\BluepayAdapter;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_bluepay';
    const VAULT_CODE = 'fiserv_bluepay_vault';


	// Temporary until Payment.JS implements ACH
	const SHPF_TPS_HASH_TYPE = 'HMAC_SHA512';
	const SHPF_TPS_DEF = 'MERCHANT TRANSACTION_TYPE AMOUNT MODE';
	const SHPF_TRANS_TYPE = 'AUTH';
	const SHPF_AMOUNT = '0.00';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var BluepayAdapter
	 */
	private $pjsAdapter;

	/**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SessionManagerInterface $session
	 */
	public function __construct(
		Config $config,
		BluepayAdapter $pjsAdapter,
		SessionManagerInterface $session
	) {
		$this->config = $config;
		$this->pjsAdapter = $pjsAdapter;
		$this->session = $session;
	}

	/**
	 * Retrieve assoc array of checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();
		$config = [
			'isActive' => $this->config->isActive($storeId),
			'accountId' => $this->config->getAccountId($storeId),
			'ccTypesMapper' => $this->config->getCcTypesMapper(),
			'environment' => $this->config->getEnvironment($storeId),
			'paymentType' => $this->config->getPaymentType($storeId),
			'paymentAction' => $this->config->getPaymentAction($storeId),
			'availableCardTypes' => $this->config->getAvailableCardTypes($storeId),
			'useCcv' => $this->config->isCcvEnabled($storeId),
			'currency' => $this->config->getCurrency($storeId),
	        'vaultCode' => self::VAULT_CODE,

		];

		if ($this->isPjsAvailable($config['paymentType'])) 
		{
			$config['pjsAuthData'] = json_encode($this->getPjsAuthData($storeId));
		}

		if ($this->isAchAvailable($config['paymentType']))
		{
			$config = array_merge($config, $this->gatherAchConfigFields($storeId));
		}

		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}

	private function gatherAchConfigFields($storeId) {
		$tps = $this->calcAchShpfTps($storeId);
		return [
			'ach_shpf_url' => $this->config->getAchShpfUrl(),
			'tps' => $this->calcAchShpfTps($storeId),
			'tps_def' => self::SHPF_TPS_DEF,
			'tps_hash_type' => self::SHPF_TPS_HASH_TYPE
		];
	}

	private function calcAchShpfTps($storeId) 
	{
		$bpSecret = $this->config->getSecretKey($storeId);
		$merchantId = $this->config->getAccountId($storeId);
		$transType = self::SHPF_TRANS_TYPE;
		$amount = self::SHPF_AMOUNT;
		$mode = $this->config->getEnvironment($storeId);

		$rawTpsData = $merchantId . $transType . $amount . $mode;
		return hash_hmac("sha512", $rawTpsData, $bpSecret);
	}

	private function isAchAvailable($paymentType) 
	{
		return strpos($paymentType, 'ACH') !== false;
	}

	private function isPjsAvailable($paymentType)
	{
		return strpos($paymentType, 'CC') !== false;
	}

	public function getPjsAuthData($storeId) 
	{
		return $this->pjsAdapter->getAuthData($storeId);
	}
}
