<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;

/**
 * Class AddressDataBuilder
 */
class AddressDataBuilder implements BuilderInterface
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

		$order = $paymentDO->getOrder();
		$billingAddress = $order->getBillingAddress();
		
		return [
			BpRequestKeys::ADDRESS_1 => $billingAddress->getStreetLine1(),
			BpRequestKeys::ADDRESS_2 => $billingAddress->getStreetLine2(),
			BpRequestKeys::LOCALITY => $billingAddress->getCity(),
			BpRequestKeys::REGION => $billingAddress->getRegionCode(),
			BpRequestKeys::POSTAL_CODE => $billingAddress->getPostcode(),
			BpRequestKeys::COUNTRY => $billingAddress->getCountryId()
		];
	}
}
