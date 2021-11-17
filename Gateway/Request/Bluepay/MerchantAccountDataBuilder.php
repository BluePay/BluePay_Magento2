<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Adds Merchant Account ID to the request.
 */
class MerchantAccountDataBuilder implements BuilderInterface
{

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(Config $config, SubjectReader $subjectReader)
	{
		$this->config = $config;
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();

		$merchantAccountId = $this->config->getAccountId($orderDO->getStoreId());

		return [ BpRequestKeys::MERCHANT_ID => $merchantAccountId ];
	}
}
