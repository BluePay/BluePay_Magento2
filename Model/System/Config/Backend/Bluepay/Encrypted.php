<?php
/**
 * Encrypted config field backend model
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Fiserv\Payments\Model\System\Config\Backend\Bluepay;

class Encrypted extends \Magento\Config\Model\Config\Backend\Encrypted
{

	/**
	 * Encrypt value before saving
	 *
	 * @return void
	 */
	public function beforeSave()
	{
		$this->_dataSaveAllowed = false;
		$value = $this->getValue();
		// don't save value, if an obscured value was received. This indicates that data was not changed.
		if (!preg_match('/^\*+$/', $value) && !empty($value)) {
			
			$enc_source = $this->getPath();
			switch ($enc_source) {
				case "payment/fiserv_bluepay/account_id":
					$this->validateAccountId($value);
					break;
				case "payment/fiserv_bluepay/secret_key":
					$this->validateSecretKey($value);
					break;
			}

			$this->_dataSaveAllowed = true;
			$encrypted = $this->_encryptor->encrypt($value);
			$this->setValue($encrypted);
		} else {
			$this->_dataSaveAllowed = false;
		}
	}

	private function validateAccountId($value) {
		if (strlen($value) != 12) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. Account ID must be 12 digits and begin with 100. Your settings have not been saved."));
		}
	}

	private function validateSecretKey($value) {
		if (strlen($value) != 32) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. Secret Key must be 32 digits. Your settings have not been saved."));
		}
	}
}
