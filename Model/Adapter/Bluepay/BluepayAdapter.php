<?php
namespace Fiserv\Payments\Model\Adapter\Bluepay;

use Fiserv\Payments\Model\Adapter\PjsAdapter;
use Fiserv\Payments\Gateway\Config\Config as PjsConfig;
use Fiserv\Payments\Gateway\Config\Bluepay\Config as BluepayConfig;
use Fiserv\Payments\Model\Source\Bluepay\TransactionMode;

class BluepayAdapter extends PjsAdapter
{
	const KEY_BP_GATEWAY = "BLUEPAY";
	/**
	 * @var Config
	 */
	protected $bpConfig;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		PjsConfig $pjsConfig,
		BluepayConfig $bpConfig
	) {
		$this->bpConfig = $bpConfig;
		parent::__construct($pjsConfig);
	}

	/**
	 * Retrieve assoc array of Payment.JS
	 * authorization information
	 *
	 * @param string $storeId
	 * @return array
	 */
	protected function getGatewayData($storeId) 
	{
		$data = [];
		$data[self::KEY_GATEWAY] = self::KEY_BP_GATEWAY;
		$data[self::KEY_ACCOUNT_ID] = $this->bpConfig->getAccountId($storeId);
		$data[self::KEY_SECRET_KEY] = $this->bpConfig->getSecretKey($storeId);
		$data[self::KEY_ZERO_AUTH] = false;
		
		return $data;
	}

	/**
	 * Returns 'sandbox' or 'production'
	 * based on gateway's environment
	 *
	 * @param string $storeId
	 * @return string
	 */
	protected function getEnvironment($storeId) 
	{
		$env = $this->bpConfig->getEnvironment($storeId);

		if ($env == TransactionMode::TRANS_MODE_LIVE) {
			return self::PROD_ENV;
		} else if ($env == TransactionMode::TRANS_MODE_TEST) {
			return self::SANDBOX_ENV;
		}
	}
}