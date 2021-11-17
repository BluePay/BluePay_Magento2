<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\Bluepay;

use Fiserv\Payments\Lib\Bluepay\BpRequestKeys;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Gateway\Subject\Bluepay\SubjectReader;
use Magento\Payment\Helper\Formatter;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
/**
 * Class LevelThreeDataBuilder
 */
class LevelThreeDataBuilder implements BuilderInterface
{
	use Formatter;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();
		$shippingAddress = $orderDO->getShippingAddress();
		$order = $paymentDO->getPayment()->getOrder();

		$result = [
			BpRequestKeys::SHIPPING_AMOUNT => $order->getShippingAmount(),
			BpRequestKeys::DISCOUNT_AMOUNT => $order->getDiscountAmount(),
			BpRequestKeys::TAX_ID => $order->getCustomerTaxvat(),
			BpRequestKeys::BUYER_NAME => $this->buildFullName($orderDO->getBillingAddress()),
			BpRequestKeys::SHIP_NAME => $this->buildFullName($shippingAddress),
			BpRequestKeys::SHIP_STREET => $shippingAddress->getStreetLine1(),
			BpRequestKeys::SHIP_LOCALITY => $shippingAddress->getCity(),
			BpRequestKeys::SHIP_REGION => $shippingAddress->getRegionCode(),
			BpRequestKeys::SHIP_POSTAL_CODE => $shippingAddress->getPostcode(),
			BpRequestKeys::SHIP_COUNTRY => $shippingAddress->getCountryId()
		];

		$orderItems = $orderDO->getItems();
		if (count($orderItems) < 1) {
			throw new \InvalidArgumentException('No items found in order.');
		}

		if (count($orderItems) > 0) {
			$taxPercent = reset($orderItems)->getTaxPercent() . "%";
			$result[BpRequestKeys::TAX_RATE] = $taxPercent;

			$lv3Info = $this->createLevelThreeInfo($orderItems);
			return array_replace_recursive($result, $lv3Info);
		}

		return $result;
	}

	private function buildFullName(AddressAdapterInterface $address)
	{
		$name1 = $address->getFirstname();
		$name2 = $address->getLastname();
		$fullName = ($name1 != '' && $name2 != '') ? $name1 . ' ' . $name2 : $name1 . $name2;

		return $fullName;
	}

	private function createLevelThreeInfo(array $orderItems) {
		$result = array();
		$i = 1;
		foreach ($orderItems as $item) {
			$product_code = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_PRODUCT_CODE;
			$result[$product_code] = $item->getSku();

			$unit_cost = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_UNIT_COST;
			$result[$unit_cost] = $this->formatPrice($item->getPrice());
			
			$quantity = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_QUANTITY;
			$result[$quantity] = $item->getQtyOrdered();

			$descriptor = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_DESCRIPTOR;
			$result[$descriptor] = $item->getName();

			$measure_units = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_MEASURE_UNITS;
			$result[$measure_units] = 'EA';

			$commodity_code = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_COMMODITY_CODE;
			$result[$commodity_code] = '-';

			$tax = round($item->getPrice() * ($item->getTaxPercent() / 100), 2);
			$tax_amount = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_TAX_AMOUNT;
			$result[$tax_amount] = $this->formatPrice($tax);

			$tax_rate = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_TAX_RATE;
			$result[$tax_rate] = $item->getTaxPercent() . '%';

			$item_discount = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_DISCOUNT;
			$result[$item_discount] = $this->formatPrice($item->getDiscountAmount());

			$line_item_total = BpRequestKeys::L3_ITEM_PREFIX . strval($i) . BpRequestKeys::L3_STUB_TOTAL;
			$itemTotal = $item->getPrice() * $item->getQtyOrdered() + $tax;
			$result[$line_item_total] = $this->formatPrice($itemTotal);
			$i++;
		}

		return $result;
	}}
