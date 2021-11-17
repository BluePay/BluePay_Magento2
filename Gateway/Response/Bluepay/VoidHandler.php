<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use Magento\Sales\Model\Order\Payment;

class VoidHandler extends TransactionIdHandler
{
	/**
	 * Sets payment transaction Id for non-refund, non-void transactions
	 * sets additional information for refund/void transactions
	 *
	 * @param Payment $payment
	 * @param \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse
	 * @return void
	 */
	protected function setTransactionId(Payment $payment, \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse) 
	{
		$payment->setTransactionAdditionalInfo(
			self::VOID_ID,
			$bpResponse->getTransId()
		);
	}

	/**
	 * Whether transaction should be closed
	 *
	 * @return bool
	 */
	protected function shouldCloseTransaction()
	{
		return true;
	}

	/**
	 * Whether parent transaction should be closed
	 *
	 * @param Payment $orderPayment
	 * @return bool
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function shouldCloseParentTransaction(Payment $orderPayment)
	{
		return true;
	}
}