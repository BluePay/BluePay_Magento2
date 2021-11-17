<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Http\Bluepay\Client;

use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Fiserv\Payments\Lib\Bluepay\BpResponse;
use Fiserv\Payments\Gateway\Config\Bluepay\Config;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Psr\Log\LoggerInterface;

/**
 * A client that send transaction requests to the Fiserv-BluePay Bp10emu API
 */
abstract class ClientBase implements ClientInterface
{
	const TPS_ELEMENTS = [
		BpRequestKeys::MERCHANT_ID,
		BpRequestKeys::TRANSACTION_TYPE,
		BpRequestKeys::AMOUNT,
		BpRequestKeys::PAYMENT_TOKEN,
		BpRequestKeys::MODE
	];
	const TPS_HASH_TYPE = 'HMAC_SHA512';
	const TRANSACTION_TYPE_SALE = 'SALE';
	const TRANSACTION_TYPE_AUTH = 'AUTH';
	const TRANSACTION_TYPE_CAPTURE = 'CAPTURE';
	const TRANSACTION_TYPE_VOID = 'VOID';
	const TRANSACTION_TYPE_REFUND = 'REFUND';
	const USER_AGENT_PREFIX = 'Fiserv-BluePay Magento 2 Plugin - v';
	const KEY_BP_RESPONSE = 'bpResponse';
	const RESPONSE_VERSION = '5';

	/**
	 * @var PaymentLogger
	 */
	private $paymentLogger;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var ZendClientFactory
	 */
	private $httpClientFactory;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param PaymentLogger $paymentLogger
	 * @param LoggerInterface $logger
	 * @param ZendClientFactory $httpClientFactory
	 * @param Config $config
	 */
	public function __construct(
		PaymentLogger $paymentLogger,
		LoggerInterface $logger,
		ZendClientFactory $httpClientFactory,
		Config $config
	) {
		$this->httpClientFactory = $httpClientFactory;
		$this->config = $config;
		$this->paymentLogger = $paymentLogger;
		$this->logger = $logger;
	}

	/**
	 * Places request to gateway. Returns result as BpResponse object
	 *
	 * @param TransferInterface $transferObject
	 * @return Fiserv\Payments\Lib\Bluepay\BpResponse
	 * @throws \Magento\Payment\Gateway\Http\ClientException
	 */
	public function placeRequest(TransferInterface $transferObject) 
	{
		$requestBody = $transferObject->getBody();

		$storeId = $requestBody['store_id'] ?? null;
		$requestBody[BpRequestKeys::TRANSACTION_TYPE] = $this->getTransType();
		$requestBody[BpRequestKeys::MODE] = $this->config->getEnvironment($storeId);
		$requestBody[BpRequestKeys::TPS_DEFINITION] = implode(' ', self::TPS_ELEMENTS);
		$requestBody[BpRequestKeys::TPS_HASH_TYPE] = self::TPS_HASH_TYPE;
		$requestBody[BpRequestKeys::TPS] = $this->calculateTps($requestBody);
		$requestBody[BpRequestKeys::RESPONSE_VERSION] = self::RESPONSE_VERSION;

		// Remove unnecessary data from request
		unset($requestBody['store_id']);
		return $this->postToBluepay($requestBody);
	}

	private function postToBluepay(array $requestBody) 
	{
		$log = [
			'request' => $requestBody,
		];
		$url = $this->config->getBpApiUrl();
		$userAgent = self::USER_AGENT_PREFIX . Version::getVersionString();

		$client = $this->httpClientFactory->create();
		$client->setUri($url);
		$client->setParameterPost($requestBody);
		$client->setMethod(ZendClient::POST);
		$client->setConfig([
			'maxredirects' => 0,
			'timeout' => 15,
			'useragent' => $userAgent,
		]);

		try {
			$response = $client->request();
			$bpResponse = $this->parseResponse($response);
			$log['response'] = $bpResponse[self::KEY_BP_RESPONSE]->getResponse();
			return $bpResponse;
		} catch (\Exception $e) {

			$this->logger->critical($e);
			throw new ClientException(
				__('An error occurred in the payment gateway.')
			);
		} finally {
			$this->paymentLogger->debug($log);
		}
	}

	private function parseResponse($response)
	{
		$rawResponse = substr(
			$response->getHeader('location'),
			strpos($response->getHeader('location'), "?") + 1
		);

		if ($rawResponse)
		{
			$_res = array();
			$_res[self::KEY_BP_RESPONSE] = new BpResponse($rawResponse);
			
			return $_res;
		}

		throw new \Exception('Error parsing BluePay response.');
	}
	
	private function calculateTps(array $requestBody)
	{
		$storeId = $requestBody['store_id'] ?? null;
		$secretKey = $this->config->getValue(Config::KEY_SECRET_KEY, $storeId);

		$rawTpsData = '';
		foreach (self::TPS_ELEMENTS as $field) 
		{
			$_data = isset($requestBody[$field]) ? $requestBody[$field] : '';
			$rawTpsData = $rawTpsData . $_data;
		}

		return hash_hmac("sha512", $rawTpsData, $secretKey);
	}

	/**
	 * Get transaction type data
	 * @return string
	 */
	abstract protected function getTransType();
}
