<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Framework\App\ObjectManager;

/**
 * Class CustomerDataBuilder
 */
class CustomerDataBuilder implements BuilderInterface
{
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
		$billingAddress = $orderDO->getBillingAddress();

		return [
			BpRequestKeys::FIRST_NAME => $billingAddress->getFirstname(),
			BpRequestKeys::LAST_NAME => $billingAddress->getLastname(),
			BpRequestKeys::COMPANY => $billingAddress->getCompany(),
			BpRequestKeys::PHONE => $billingAddress->getTelephone(),
			BpRequestKeys::EMAIL => $billingAddress->getEmail(),
			BpRequestKeys::IP_ADDRESS => $orderDO->getRemoteIp()
		];
	}
}
