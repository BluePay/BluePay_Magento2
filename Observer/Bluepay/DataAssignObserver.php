<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Observer\Bluepay;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
	const PAYMENT_TOKEN = 'payment_token';
	const PAYMENT_TYPE = 'payment_type';

	/**
	 * @var array
	 */
	protected $additionalInformationList = [
		self::PAYMENT_TOKEN,
		self::PAYMENT_TYPE
	];

	/**
	 * @param Observer $observer
	 * @return void
	 */
	public function execute(Observer $observer)
	{
		$data = $this->readDataArgument($observer);

		$additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
		if (!is_array($additionalData)) {
			return;
		}

		$paymentInfo = $this->readPaymentModelArgument($observer);

		foreach ($this->additionalInformationList as $additionalInformationKey) {
			if (isset($additionalData[$additionalInformationKey])) {
				$paymentInfo->setAdditionalInformation(
					$additionalInformationKey,
					$additionalData[$additionalInformationKey]
				);
			}
		}
	}
}
