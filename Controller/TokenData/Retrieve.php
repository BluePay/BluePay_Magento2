<?php /**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\TokenData;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\Webapi\Response;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Controller\Result\JsonFactory;
use \Fiserv\Payments\Model\Session;
use \Fiserv\Payments\Model\Adapter\PjsAdapter;
use \Fiserv\Payments\Controller\TokenData\Set as SetAction;

class Retrieve extends \Magento\Framework\App\Action\Action
{
	const HTTP_NOT_FOUND = 404; 
	/**
	 * @var \Magento\Framework\Controller\Result\JsonFactory
	 */
	protected $resultJsonFactory;

	/**
	 * @var \Fiserv\Payments\Model\Session
	 */
	protected $session;

	/**
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	 * @param \Fiserv\Payments\Model\Session $session
	 */
	public function __construct(
		Context $context,
		Session $session,
		JsonFactory $resultJsonFactory)
	{
		$this->resultJsonFactory = $resultJsonFactory;
		$this->session = $session;
		parent::__construct($context);
	}

	/**
	 * View  page action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		$clientToken = $this->getRequest()->getParam(PjsAdapter::KEY_PJS_RESPONSE_CLIENT_TOKEN);
		$result = $this->resultJsonFactory->create();
		$result->setHttpResponseCode(self::HTTP_NOT_FOUND);
		$resultData = [];
		
		if (isset($clientToken) && !empty($clientToken)) {
			
			// Token data will be stored in a session where the
			// session's id is the one created by the Set action.
			if ($this->session->getSessionId() != SetAction::MOCK_SESS_ID) {
				$this->session->setSessionId(SetAction::MOCK_SESS_ID);
				$this->session->start();				
			}
			
			// true parameter clears the data after retrieval
			$sessionData = $this->session->getData($clientToken, true);
			
			if (isset($sessionData) && !empty($sessionData)) {
				$result->setHttpResponseCode(Response::HTTP_OK);
				if ($sessionData["error"]) {
					$resultData["error"] = $sessionData["reason"];
				} else if (isset($sessionData["card"])) {
					$resultData["token"] = $sessionData["card"]["token"];
					$resultData["brand"] = $sessionData["card"]["brand"];
				}
			}
		} 

		return $result->setData($resultData);
	}
}