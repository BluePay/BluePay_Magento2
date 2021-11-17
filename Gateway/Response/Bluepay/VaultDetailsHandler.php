<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpResponse;
use Fiserv\Payments\Model\PaymentTokenFactory;
use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Fiserv\Payments\Gateway\Response\Bluepay\AchDetailsHandler;
use Fiserv\Payments\Gateway\Request\Bluepay\VaultDataBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Vault Details Handler
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class VaultDetailsHandler implements HandlerInterface
{
	/**
	 * @var PaymentTokenFactoryInterface
	 */
	protected $paymentTokenFactory;

	/**
	 * @var OrderPaymentExtensionInterfaceFactory
	 */
	protected $paymentExtensionFactory;

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Json
	 */
	private $serializer;

	/**
	 * VaultDetailsHandler constructor.
	 *
	 * @param PaymentTokenFactoryInterface $paymentTokenFactory
	 * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
	 * @param Config $config
	 * @param SubjectReader $subjectReader
	 * @param Json|null $serializer
	 * @throws \RuntimeException
	 */
	public function __construct(
		PaymentTokenFactoryInterface $paymentTokenFactory,
		OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
		Config $config,
		SubjectReader $subjectReader,
		Json $serializer = null
	) {
		$this->paymentTokenFactory = $paymentTokenFactory;
		$this->paymentExtensionFactory = $paymentExtensionFactory;
		$this->config = $config;
		$this->subjectReader = $subjectReader;
		$this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		/** @var \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse */
		$bpResponse = $this->subjectReader->readBpResponse($response);
		$payment = $paymentDO->getPayment();

		if ($payment->getStoreVault() === VaultDataBuilder::KEY_STORE_TRUE) {

			$paymentToken = null;
			if ($bpResponse->getPaymentType() === "CREDIT") {
				$paymentToken = $this->getVaultCardToken($bpResponse);
			} elseif ($bpResponse->getPaymentType() === "ACH") {
				$paymentToken = $this->getVaultAccountToken($bpResponse);
			}

			if ($paymentToken !== null) {
				$extensionAttributes = $this->getExtensionAttributes($payment);
				$extensionAttributes->setVaultPaymentToken($paymentToken);
			}
		}
	}

	/**
	 * Get vault payment token entity for payment card
	 *
	 * @param \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse
	 * @return PaymentTokenInterface|null
	 */
	protected function getVaultCardToken(BpResponse $bpResponse)
	{
		/** @var PaymentTokenInterface $paymentToken */
		$paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);

		$details = $this->convertDetailsToJSON([
			'type' => $this->getCreditCardType($bpResponse->getCardType()),
			'maskedCC' => $bpResponse->getMaskedAccount(),
			'expirationDate' => $bpResponse->getCcExpireMonth() . "/" . $bpResponse->getCcExpireYear()
		]);

		$paymentToken->setGatewayToken($bpResponse->getMasterId());
		$paymentToken->setExpiresAt($this->getExpirationDate($bpResponse));
		$paymentToken->setTokenDetails($details);

		return $paymentToken;
	}

	/**
	 * Get vault payment token entity for ach account
	 *
	 * @param \Fiserv\Payments\Lib\Bluepay\BpResponse $bpResponse
	 * @return PaymentTokenInterface|null
	 */
	protected function getVaultAccountToken(BpResponse $bpResponse)
	{
		$paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_ACH);

		$details = $this->convertDetailsToJSON(
			$this->parseAccountString($bpResponse->getMaskedAccount())
		);

		$paymentToken->setTokenDetails($details);
		$paymentToken->setGatewayToken($bpResponse->getMasterId());
		$expDate = date('Y-m-d 00:00:00', strtotime('+1 year'));
		$paymentToken->setExpiresAt($expDate);

		return $paymentToken;
	}

	private function parseAccountString($acctString)
	{
		$_r = array();
		preg_match(AchDetailsHandler::ACCOUNT_PATTERN, $acctString, $_r);
		
		if (count($_r) < 4)
		{
			throw new \InvalidArgumentException('Unable to parse ACH account string from BluePay.');
		}
		
		return [
			AchDetailsHandler::ACCOUNT_TYPE => $this->mapAccountType($_r[1]),
			AchDetailsHandler::ROUTING_NUMBER => $_r[2],
			AchDetailsHandler::BIN => $_r[3]
		];
	}

	private function mapAccountType($acctType) {
		if (strtoupper($acctType) == AchDetailsHandler::SAVINGS_CODE)
		{
			return AchDetailsHandler::SAVINGS;
		}
		if (strtoupper($acctType) == AchDetailsHandler::CHECKING_CODE)
		{
			return AchDetailsHandler::CHECKING;
		}

		throw new \InvalidArgumentException('Unable to identify BluePay ACH account type.');
	}

	/**
	 * @param BpResponse $bpResponse
	 * @return string
	 */
	private function getExpirationDate($bpResponse)
	{
		$expDate = new \DateTime(
            $bpResponse->getCcExpireYear()
            . '-'
            . $bpResponse->getCcExpireMonth()
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));
        
        return $expDate->format('Y-m-d 00:00:00');
	}

	/**
	 * Convert payment token details to JSON
	 * @param array $details
	 * @return string
	 */
	private function convertDetailsToJSON($details)
	{
		$json = $this->serializer->serialize($details);
		return $json ? $json : '{}';
	}

	/**
	 * Get type of credit card mapped from Bluepay
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

	/**
	 * Get payment extension attributes
	 * @param InfoInterface $payment
	 * @return OrderPaymentExtensionInterface
	 */
	private function getExtensionAttributes(InfoInterface $payment)
	{
		$extensionAttributes = $payment->getExtensionAttributes();
		if (null === $extensionAttributes) {
			$extensionAttributes = $this->paymentExtensionFactory->create();
			$payment->setExtensionAttributes($extensionAttributes);
		}
		return $extensionAttributes;
	}
}
