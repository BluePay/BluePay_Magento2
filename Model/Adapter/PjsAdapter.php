<?php
namespace Fiserv\Payments\Model\Adapter;

use Fiserv\Payments\Gateway\Config\Config;

abstract class PjsAdapter
{
	const CONTENT_TYPE = 'application/json';
	const PROD_ENV = 'production';
	const SANDBOX_ENV = 'sandbox';
	const KEY_PJS_RESPONSE_CLIENT_TOKEN = 'Client-Token';
	const KEY_NONCE = 'Nonce';
	const KEY_PUBLIC_KEY = 'publicKeyBase64';
	const KEY_CLIENT_TOKEN = 'clientToken';
	const KEY_GATEWAY = 'gateway';
	const KEY_ACCOUNT_ID = 'accountId';
	const KEY_SECRET_KEY = 'secretKey';
	const KEY_ZERO_AUTH = 'zeroDollarAuth';
	const AUTH_ENDPOINT = 'merchant/authorize-session';
	/**
	 * @var Config
	 */
	protected $pjsConfig;

	/**
	 * @var string
	 */
	protected $nonce;

	/**
	 * @var string
	 */
	protected $timestamp;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config
	) {
		$this->pjsConfig = $config;
	}

	/**
	 * Retrieve assoc array of Payment.JS
	 * authorization information
	 *
	 * @param string $storeId
	 * @return array
	 */
	public function getAuthData($storeId)
	{
		$this->timestamp = $this->getTimestamp();
		$this->nonce = $this->getnonce($this->timestamp);

		$url = $this->getServiceUrl($storeId) . '/' . self::AUTH_ENDPOINT;
		$data = $this->getGatewayData($storeId);
		$payload = json_encode($data);

		return $this->sendAuthRequest($payload, $url);
	}

	protected function sendAuthRequest($payload, $url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders($payload));
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		$curlResponse = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		return $this->parsePjsResponse($curlResponse, $statusCode, $headerLength);
	}

	protected function getHeaders($payload) {
		return [
			'Api-Key: ' . $this->getPjsKey(),
			'Content-Type: ' . self::CONTENT_TYPE,
			'Content-Length: ' . strlen($payload),
			'Message-Signature: ' . $this->createSignature($payload),
			'Nonce: ' . $this->nonce,
			'Timestamp: ' . $this->timestamp
		];
	}

	/** 
	* Returns Payment.JS service url
	* based on gateway's environment
	* 
	* @param string $storeId
	* @return string
	*/ 
	protected function getServiceUrl($storeId) {
		$env = $this->getEnvironment($storeId);
		if ($env == self::PROD_ENV) {
			return $this->pjsConfig->getPjsProdUrl();
		} else if ($env == self::SANDBOX_ENV) {
			return $this->pjsConfig->getPjsSandboxUrl();
		}
	}

	protected function createSignature($payload) {
		$msg = $this->getPjsKey() . $this->nonce . $this->timestamp . $payload;
		return base64_encode(hash_hmac('sha256', $msg, $this->getPjsSecret()));
	}

	protected function getPjsKey() {
		return $this->pjsConfig->getPjsKey();
	}

	protected function getPjsSecret() {
		return $this->pjsConfig->getPjsSecret();
	}

	protected function getNonce($timestamp) {
		return $timestamp + rand();
	}

	protected function getTimestamp() {
		return time() * 1000;
	}

	protected function parsePjsResponse($response, $statusCode, $headerLength) {
		$header = [];
		foreach(explode("\r\n", trim(substr($response, 0, $headerLength))) as $row) {
			if(preg_match('/(.*?): (.*)/', $row, $matches)) {
				$header[$matches[1]] = $matches[2];
			}
		}

		$clientToken = $header[self::KEY_PJS_RESPONSE_CLIENT_TOKEN];
		$responseNonce =  $header[self::KEY_NONCE];

		$body = substr($response, $headerLength);
		$publicKey = substr($body, 20, -2);
		
		$data = [];
		if ($statusCode === 200) {
			if ($responseNonce == $this->nonce) {
				$data[self::KEY_CLIENT_TOKEN] = $clientToken; 
				$data[self::KEY_PUBLIC_KEY] = $publicKey;
			} else {
				throw new Exception('Payment.JS nonce validation failed: "' + $this->nonce + '"', 1);
			 }	
		} else {
			throw new Exception('HTTP Error Code: ' + $statusCode, 1);
		};

		return $data;
	}

	/**
	 * Retrieve assoc array of Payment.JS
	 * authorization information
	 *
	 * @param string $storeId
	 * @return array
	 */
	abstract protected function getGatewayData($storeId);

	/**
	 * Returns 'sandbox' or 'production'
	 * based on gateway's environment
	 *
	 * @param string $storeId
	 * @return string
	 */
	abstract protected function getEnvironment($storeId);
}