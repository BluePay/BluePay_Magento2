<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\Bluepay;

use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Validates the status of an attempted transaction
 */
class TransactionResponseValidator extends AbstractValidator
{
	const BP_SUCCESS_RESPONSE = 'APPROVED';
	const BP_ERROR_RESPONSE = "ERROR";
	const BP_DECLINED_RESPONSE = "DECLINED";
	const BP_MISSING_RESPONSE = "MISSING";
	
	private $bpSuccessResponses = [
		self::BP_SUCCESS_RESPONSE
	];

	private $bpFailureResponses = [
		self::BP_ERROR_RESPONSE, 
		self::BP_DECLINED_RESPONSE, 
		self::BP_MISSING_RESPONSE
	];

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader)
	{
		parent::__construct($resultFactory);
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function validate(array $validationSubject): ResultInterface
	{
		$bpResponse = $this->subjectReader->readBpResponseFromResponse($validationSubject);

		if (!$this->isSuccessful($bpResponse)) {
			$errorMessages = [];
			$errorCodes = [];
			array_push($errorMessages, $bpResponse->getMessage());
			array_push($errorCodes, $bpResponse->getStatus());

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		return $this->createResult(true);
	}


	private function isSuccessful(\Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse) 
	{
		$status = $bpResponse->getStatus();
		return (
			in_array($status, $this->bpSuccessResponses) && 
			!in_array($status, $this->bpFailureResponses)
		);
	}
}
