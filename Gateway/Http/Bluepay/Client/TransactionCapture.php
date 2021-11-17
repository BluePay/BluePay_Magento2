<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Http\Bluepay\Client;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;

/**
 * A client that send transaction requests to the Fiserv-BluePay Bp10emu API
 */
class TransactionCapture Extends ClientBase
{
	protected function getTransType() 
	{
		return self::TRANSACTION_TYPE_CAPTURE;
	}
}
