<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Bluepay;

use Fiserv\Payments\Model\Config\ConfigProvider as PjsConfigProvider;
use Fiserv\Payments\Model\Config\Bluepay\ConfigProvider as GatewayConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Payment
 *
 * @api
 * @since 100.1.0
 */
class Payment extends Template
{
	/**
	 * @var Fiserv\Payments\Model\Config\ConfigProvider
	 */
	private $pjsConfigProvider;

	/**
	 * @var Fiserv\Payments\Model\Config\Bluepay\ConfigProvider
	 */
	private $gatewayConfigProvider;

    /**
     * @var Json
     */
    private $json;

	/**
	 * Constructor
	 *
	 * @param Context $context
	 * @param PjsConfigProvider $pjsConfigProvider
	 * @param GatewayConfigProvider $gatewayConfigProvider
	 * @param array $data
	 */
	public function __construct(
		Context $context,
		PjsConfigProvider $pjsConfigProvider,
		GatewayConfigProvider $gatewayConfigProvider,
        Json $json,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->pjsConfigProvider = $pjsConfigProvider;
		$this->gatewayConfigProvider = $gatewayConfigProvider;
		$this->json = $json;
	}

	/**
	 * @return json object
	 */
	public function getPjsConfig()
	{
		$config = $this->pjsConfigProvider->getConfig();
		return $this->json->serialize($config);
	}

	public function getGatewayConfig() 
	{
		$config = $this->gatewayConfigProvider->getConfig();
		return $this->json->serialize($config);
	}

	/**
	 * @return json object
	 */
	public function getCode()
	{
		return GatewayConfigProvider::CODE;
	}
}
