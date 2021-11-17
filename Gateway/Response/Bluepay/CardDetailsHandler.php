<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class CardDetailsHandler
 */
class CardDetailsHandler implements HandlerInterface
{
	const ACH_PAYMENT_TYPE = 'ACH';
	const CARD_PAYMENT_TYPE = 'CREDIT';
	const CARD_NUMBER = "cc_number";

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
	public function __construct(
		Config $config,
		SubjectReader $subjectReader
	) {
		$this->config = $config;
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$bpResponse = $this->subjectReader->readBpResponse($response);
		$pType = $bpResponse->getPaymentType();
		if ($pType == self::CARD_PAYMENT_TYPE) 
		{
			$paymentDO = $this->subjectReader->readPayment($handlingSubject);
			$payment = $paymentDO->getPayment();
			ContextHelper::assertOrderPayment($payment);

			$maskedAccount = $bpResponse->getMaskedAccount();
			$bin = str_replace(["X"], "", $maskedAccount);
			$bpCardType = $bpResponse->getCardType();
			
			$payment->setCcLast4($bin);
			$payment->setCcExpMonth($bpResponse->getCcExpireMonth());
			$payment->setCcExpYear($bpResponse->getCcExpireYear());
			$payment->setCcType($this->getCreditCardType($bpCardType));
			$payment->setAdditionalInformation(self::CARD_NUMBER, $maskedAccount);
			$payment->setAdditionalInformation(OrderPaymentInterface::CC_TYPE, $bpCardType);
		}
	}

	/**
	 * Get type of credit card mapped from BluePay
	 *
	 * @param string $type
	 * @return array
	 */
	private function getCreditCardType($type)
	{
		$replaced = str_replace(' ', '-', strtolower($type));
		$mapper = $this->config->getCcTypesMapper();

		return $mapper[strtoupper($replaced)];
	}
}
