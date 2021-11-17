<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class AchDetailsHandler
 */
class AchDetailsHandler implements HandlerInterface
{
	const ACH_PAYMENT_TYPE = 'ACH';
	const CARD_PAYMENT_TYPE = 'CREDIT';
	const ACCOUNT_NUMBER = "account_number";
	const ACCOUNT_TYPE = "account_type";
	const ROUTING_NUMBER = "routing_number";
	const BIN = "ach_bin";
	const SAVINGS_CODE = "S";
	const CHECKING_CODE = "C";
	const SAVINGS = "savings";
	const CHECKING = "checking";
	const ACCOUNT_PATTERN = "/\A([C|S]):(\d*):X*(\d{4})\z/";

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
		$bpResponse = $this->subjectReader->readBpResponse($response);
		$pType = $bpResponse->getPaymentType();
		if ($pType == self::ACH_PAYMENT_TYPE) 
		{
			$paymentDO = $this->subjectReader->readPayment($handlingSubject);
			$payment = $paymentDO->getPayment();
			ContextHelper::assertOrderPayment($payment);

			$maskedAccount = $bpResponse->getMaskedAccount();
			$acctInfo = $this->parseAccountString($maskedAccount);

			$payment->setEcheckAccountType($acctInfo[self::ACCOUNT_TYPE]);
			$payment->setEcheckRoutingNumber($acctInfo[self::ROUTING_NUMBER]);
			$payment->setEcheckAccountName($maskedAccount);
			$payment->setAdditionalInformation(self::BIN, $acctInfo[self::BIN]);
		}
	}

	private function parseAccountString($acctString)
	{
		$_r = array();
		preg_match(self::ACCOUNT_PATTERN, $acctString, $_r);
		
		if (count($_r) < 4)
		{
			throw new \InvalidArgumentException('Unable to parse ACH account string from BluePay.');
		}
		
		return [
			self::ACCOUNT_TYPE => $this->mapAccountType($_r[1]),
			self::ROUTING_NUMBER => $_r[2],
			self::BIN => $_r[3]
		];
	}

	private function mapAccountType($acctType) {
		if (strtoupper($acctType) == self::SAVINGS_CODE)
		{
			return self::SAVINGS;
		}
		if (strtoupper($acctType) == self::CHECKING_CODE)
		{
			return self::CHECKING;
		}

		throw new \InvalidArgumentException('Unable to identify BluePay ACH account type.');
	}
}
