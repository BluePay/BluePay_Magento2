<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use \Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use \Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use \Fiserv\Payments\Observer\Bluepay\DataAssignObserver;
use \Magento\Payment\Gateway\Request\BuilderInterface;
use \Magento\Payment\Helper\Formatter;
use \Fiserv\Payments\Gateway\Response\Bluepay\TransactionIdHandler;

/**
 * Refund Data Builder
 */
class RefundDataBuilder implements BuilderInterface
{
	use Formatter;

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

		/** @var Payment $payment */
		$payment = $paymentDO->getPayment();

		$amount = null;
		try {
			$amount = $this->formatPrice($this->subjectReader->readAmount($buildSubject));
		} catch (\InvalidArgumentException $e) {
			// pass
		}

		$result = [];
		$paymentToken = $payment->getParentTransactionId() ?: $payment->getLastTransId();
		$captureToRefund = $payment->getAuthorizationTransaction()->getAdditionalInformation('bluepay_capture_transaction_id');
		if (!empty($captureToRefund)) {
			$paymentToken = $captureToRefund;
		}
		$result[BpRequestKeys::PAYMENT_TOKEN] = $paymentToken;
		$result[BpRequestKeys::AMOUNT] = $amount;

		return $result;
	}
}
