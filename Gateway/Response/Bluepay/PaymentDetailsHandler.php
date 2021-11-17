<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use Fiserv\Payments\Bluepay\Observer\DataAssignObserver;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class PaymentDetailsHandler
 */
class PaymentDetailsHandler implements HandlerInterface
{
	const STATUS = 'bluepay_status';
	const PAYMENT_TYPE = 'payment_type';
	const MESSAGE = 'bluepay_message';
	const AVS_RESPONSE = 'avs_response';
	const CVV2_RESPONSE = 'cvv_response';
	const AUTH_CODE = 'processor_auth_code';
	const MASTER_ID = 'bluepay_master_transaction_id';
	const RESPONSE_ARRAY = 'bluepay_raw_response';
	const CARD_PAYMENT_TYPE = 'CREDIT';


	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		SubjectReader $subjectReader
	) {
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();

		/** @var \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse */
		$bpResponse = $this->subjectReader->readBpResponse($response);

		$transId = $bpResponse->getTransId();
		$payment->setLastTransId($transId);
		
		$pType = $bpResponse->getPaymentType();
		if ($pType == self::CARD_PAYMENT_TYPE) 
		{
			$payment->setCcTransId($transId);
		} 

		$masterId = $bpResponse->getMasterId();
		if ($masterId != null && $payment->getParentTransactionId() == null)
		{
			$payment->setParentTransactionId($masterId);
		}
		
		$payment->setTransactionAdditionalInfo(
			self::STATUS,
			$bpResponse->getStatus()
		);

		$payment->setTransactionAdditionalInfo(
			self::PAYMENT_TYPE,
			$bpResponse->getPaymentType()
		);

		$payment->setTransactionAdditionalInfo(
			self::MESSAGE,
			$bpResponse->getMessage()
		);

		$payment->setTransactionAdditionalInfo(
			self::AVS_RESPONSE,
			$bpResponse->getAVSResponse()
		);

		$payment->setTransactionAdditionalInfo(
			self::CVV2_RESPONSE,
			$bpResponse->getCVV2Response()
		);

		$payment->setTransactionAdditionalInfo(
			self::AUTH_CODE,
			$bpResponse->getAuthCode()
		);

		$payment->setTransactionAdditionalInfo(
			self::MASTER_ID,
			$bpResponse->getMasterId()
		);

		$payment->setTransactionAdditionalInfo(
			self::RESPONSE_ARRAY,
			$bpResponse->getResponse()
		);
	}
}
