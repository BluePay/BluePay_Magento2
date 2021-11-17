<?php /**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\TokenData;

use \Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Webapi\Response;
use \Magento\Framework\Controller\Result\RawFactory;
use \Fiserv\Payments\Model\Session;
use \Fiserv\Payments\Model\Adapter\PjsAdapter;

/**
 * Set controller
 *
 * @method \Magento\Framework\App\RequestInterface getRequest()
 * @method \Magento\Framework\App\Response\Http getResponse()
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Set extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
	const HTTP_UNAUTHORIZED = 401;
	const MOCK_SESS_ID = 'eec9cd064f9111ebae930242ac130002';

	/**
	 * @var \Magento\Framework\Controller\Result\RawFactory
	 */
	protected $rawResultFactory;

	/**
	 * @var \Fiserv\Payments\Model\Session
	 */
	protected $session;

	/**
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\Controller\Result\RawFactory $rawResultFactory
	 * @param \Fiserv\Payments\Model\Session $session
	 */
	public function __construct(
		Context $context,
		Session $session,
		RawFactory $rawResultFactory)
	{
		$this->rawResultFactory = $rawResultFactory;
		$this->session = $session;
		parent::__construct($context);
	}

	/**
	 * View page action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		$result = $this->rawResultFactory->create();
		$rawJson = $this->getRequest()->getContent();
		$clientToken = $this->extractClientToken();

		if (empty($rawJson) || empty($clientToken)) {
			$result->setContents('Unauthorized');
			$result->setHttpResponseCode(self::HTTP_UNAUTHORIZED);
			return $result;
		}

		if ($this->session->getSessionId() != self::MOCK_SESS_ID) {
			$this->session->setSessionId(self::MOCK_SESS_ID);
			$this->session->start();
		}

		$data = json_decode($rawJson, true);
		$this->session->setData($clientToken, $data);

		$result->setHttpResponseCode(Response::HTTP_OK);
		return $result->setContents('');
	}

	private function extractClientToken() {
		return $this->getRequest()->getHeader(PjsAdapter::KEY_PJS_RESPONSE_CLIENT_TOKEN);
	}

	public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
	{
		return null;
	}
		
	public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}
}