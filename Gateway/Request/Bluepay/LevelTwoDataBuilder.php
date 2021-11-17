<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Helper\Formatter;

/**
 * Class LevelTwoDataBuilder
 */
class LevelTwoDataBuilder implements BuilderInterface
{
	use Formatter;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();
		$order = $paymentDO->getPayment()->getOrder();


		return [
			BpRequestKeys::AMOUNT_TAX => $this->formatPrice($order->getTaxAmount()),
			BpRequestKeys::INVOICE_ID => $orderDO->getOrderIncrementId()
		];
	}
}
