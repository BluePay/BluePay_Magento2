<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Customer;

use Fiserv\Payments\Model\PaymentTokenFactory;
use Magento\Vault\Block\Customer\PaymentTokens;

/**
 * Class AccountTokens
 *
 * @api
 * @since 100.2.0
 */
class AchTokens extends PaymentTokens
{
    /**
     * @inheritdoc
     * @since 100.2.0
     */
    public function getType()
    {
        return PaymentTokenFactory::TOKEN_TYPE_ACH;
    }
}
