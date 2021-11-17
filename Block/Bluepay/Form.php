<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Bluepay;

use Fiserv\Payments\Gateway\Config\Bluepay\Config as GatewayConfig;
use Fiserv\Payments\Model\Config\Bluepay\ConfigProvider as GatewayConfigProvider;
use Fiserv\Payments\Model\Config\ConfigProvider as PjsConfigProvider;
use Fiserv\Payments\Model\Source\Bluepay\CcType;
use Magento\Framework\View\Element\Template\Context;
use Magento\Backend\Model\Session\Quote;
use Magento\Payment\Block\Form\Cc;
use Magento\Payment\Model\Config;

/**
 * Class Form
 */
class Form extends Cc
{
	const KEY_ACH = 'ach';
	const KEY_CARD = 'payment-card';
	/**
	 * @var Quote
	 */
	protected $sessionQuote;

	/**
	 * @var Config
	 */
	protected $gatewayConfig;

	/**
	 * @var CcType
	 */
	protected $ccType;

	/**
	 * @param Context $context
	 * @param Config $paymentConfig
	 * @param Quote $sessionQuote
	 * @param GatewayConfig $gatewayConfig
	 * @param CcType $ccType
	 */
	public function __construct(
		Context $context,
		Config $paymentConfig,
		Quote $sessionQuote,
		GatewayConfig $gatewayConfig,
		CcType $ccType,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
		$this->sessionQuote = $sessionQuote;
		$this->gatewayConfig = $gatewayConfig;
		$this->ccType = $ccType;
	}

	/**
	 * Get list of available card types of order billing address country
	 * @return array
	 */
	public function getCcAvailableTypes()
	{
		return $this->getConfiguredCardTypes();
	}

	/**
	 * Check if cvv validation is available
	 * @return boolean
	 */
	public function useCcv()
	{
		return $this->gatewayConfig->isCcvEnabled($this->sessionQuote->getStoreId());
	}

	/**
	 * Get available card types
	 * @return array
	 */
	private function getConfiguredCardTypes()
	{
		$types = $this->ccType->getAllowedTypes();
		$configCardTypes = array_fill_keys(
			$this->gatewayConfig->getAvailableCardTypes($this->sessionQuote->getStoreId()),
			''
		);

		return array_intersect_key($types, $configCardTypes);
	}

	public function getPaymentTypes()
	{
		$_types = $this->gatewayConfig->getPaymentType();
		$paymentTypes = array();
		
		if (strpos($_types, "CC") !== false)
		{
			$paymentTypes[self::KEY_CARD] = "Payment Card";
		}
		if (strpos($_types, "ACH") !== false)
		{
			$paymentTypes[self::KEY_ACH] = "E-Check";
		}

		error_log(print_r($paymentTypes, true));
		return $paymentTypes;
	}

	public function canAch()
	{
		return isset($this->getPaymentTypes()[self::KEY_ACH]);
	}

	public function canPaymentCard()
	{
		return isset($this->getPaymentTypes()[self::KEY_CARD]);
	}
}
