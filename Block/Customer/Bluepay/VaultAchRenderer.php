<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Customer\Bluepay;

use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Fiserv\Payments\Model\Config\Bluepay\ConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;
use Magento\Payment\Model\MethodInterface;
use Fiserv\Payments\Model\PaymentTokenFactory;

/**
 * Class VaultTokenRenderer
 *
 * @api
 * @since 100.1.3
 */
class VaultAchRenderer extends AbstractTokenRenderer
{
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Initialize dependencies.
	 *
	 * @param Template\Context $context
	 * @param Config $config
	 * @param array $data
	 */
	public function __construct(
		Template\Context $context,
		Config $config,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->config = $config;
	}

	/**
	 * Can render specified token
	 *
	 * @param PaymentTokenInterface $token
	 * @return boolean
	 * @since 100.1.3
	 */
	public function canRender(PaymentTokenInterface $token)
	{
		$result = false;
		if ($token->getPaymentMethodCode() == ConfigProvider::CODE) {
			if ($this->config->getPaymentAction() != MethodInterface::ACTION_AUTHORIZE) {
				$result = true;
			}
		}
		return $result;
	}

	/**
	 * Get account type of ACH token
	 * Either "checking" or "savings"
	 * @return string
	 * @since 100.1.3
	 */
	public function getAchAccountType()
	{
		return $this->getTokenDetails()['account_type'];
	}

	/**
	 * Get routing number of ACH token
	 * @return string
	 * @since 100.1.3
	 */
	public function getAchRoutingNumber()
	{
		return $this->getTokenDetails()['routing_number'];
	}

	/**
	 * Get the last 4 digits of the account 
	 * associated with the ACH token
	 * @return string
	 * @since 100.1.3
	 */
	public function getAchBin()
	{
		return $this->getTokenDetails()['ach_bin'];
	}

	/**
	 * Get string summary of Bluepay ACH Token
	 * @return string
	 * @since 100.1.3
	 */
	public function getAchAccountString() 
	{
		return ucfirst($this->getAchAccountType()) . ": " . $this->getAchRoutingNumber() . ":xxxx" . $this->getAchBin();
	}

	/**
	 * Get summary of Bluepay Token
	 * @return string
	 * @since 100.1.3
	 */
	public function getTokenSummary() {
		return $this->getAchRoutingNumber() . ":" . $this->getAchBin();
	}

    /**
     * @inheritdoc
     * @since 100.1.3
     */
    public function getIconUrl()
    {
        return "";
    }

    /**
     * @inheritdoc
     * @since 100.1.3
     */
    public function getIconHeight()
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @since 100.1.3
     */
    public function getIconWidth()
    {
        return 0;
    }
}
