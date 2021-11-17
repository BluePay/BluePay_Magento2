<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use \Magento\Sales\Model\Order\Payment;
use \Fiserv\Payments\Lib\Bluepay\BpResponse;

class SettleHandler extends TransactionIdHandler
{
	/**
	 * Sets payment transaction Id for non-refund, non-void transactions
	 * sets additional information for refund/void transactions
	 *
	 * @param Payment $payment
	 * @param \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse
	 * @return void
	 */
	protected function setTransactionId(Payment $payment, BpResponse $bpResponse) 
	{
		$payment->setTransactionAdditionalInfo(
			self::CAPTURE_ID,
			$bpResponse->getTransId()
		);
	}
}