<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Fiserv\Payments\Observer\Bluepay\DataAssignObserver;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Payment Data Builder
 */
class PaymentDataBuilder implements BuilderInterface
{
	use Formatter;

	const DOCUMENT_TYPE = 'PPD';
	const ACH_PAYMENT_TYPE = 'ACH';
	const CREDIT_PAYMENT_TYPE = 'CREDIT';

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$docType = $this->getDocType($payment->getAdditionalInformation(
				DataAssignObserver::PAYMENT_TYPE
			));
		$result = [
			BpRequestKeys::AMOUNT => $this->formatPrice($orderDO->getGrandTotalAmount()),
			BpRequestKeys::PAYMENT_TOKEN => $payment->getAdditionalInformation(
				DataAssignObserver::PAYMENT_TOKEN
			),
			BpRequestKeys::ORDER_ID => $orderDO->getOrderIncrementId()
		];

		if (!empty($docType)) {
			$result[BpRequestKeys::DOCUMENT_TYPE] = $docType;
		}

		return $result;
	}

	private function getDocType(string $paymentType) {
		if ($paymentType == self::ACH_PAYMENT_TYPE) {
			return self::DOCUMENT_TYPE;
		}
		return null;
	}
}
