<?php

/**
 * BillSafe payment method
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * @copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdbs extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /** @var  $_code string payment method code */
    protected $_code = 'hcdbs';
    /** @var $_canRefund bool   */
    protected $_canRefund = false;
    /** @var $_canRefundInvoicePartial bool  */
    protected $_canRefundInvoicePartial = false;

    /** @var $_basketApiHelper HeidelpayCD_Edition_Helper_BasketApi  */
    protected $_basketApiHelper;

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdbs constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_basketApiHelper =    Mage::helper('hcd/basketApi');
    }

    /**
     * @inheritdoc
     */
    public function isAvailable($quote = null)
    {
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        if (($billing->getFirstname() != $shipping->getFirstname()) or
            ($billing->getLastname() != $shipping->getLastname()) or
            ($billing->getStreet() != $shipping->getStreet()) or
            ($billing->getPostcode() != $shipping->getPostcode()) or
            ($billing->getCity() != $shipping->getCity()) or
            ($billing->getCountry() != $shipping->getCountry())
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * @inheritdoc
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("BillSafe Info Text");

        $replace = array(
            '{LEGALNOTE}' => $paymentData['CRITERION_BILLSAFE_LEGALNOTE'],
            '{AMOUNT}' => $paymentData['CRITERION_BILLSAFE_AMOUNT'],
            '{CURRENCY}' => $paymentData['CRITERION_BILLSAFE_CURRENCY'],
            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CRITERION_BILLSAFE_RECIPIENT'],
            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CRITERION_BILLSAFE_IBAN'],
            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CRITERION_BILLSAFE_BIC'],
            '{IDENTIFICATION_SHORTID}' => $paymentData['CRITERION_BILLSAFE_REFERENCE'],
            '{PERIOD}' => $paymentData['CRITERION_BILLSAFE_PERIOD']
        );

        $loadSnippet = strtr($loadSnippet, $replace);


        return $loadSnippet;
    }

    /**
     * @inheritdoc
     */
    public function processingTransaction($order, $data, $message='')
    {
        $message = 'BillSafe Id: ' . $data['CRITERION_BILLSAFE_REFERENCE'];
        parent::processingTransaction($order, $data, $message);
    }

    /**
     * Prepare basket items for BillSafe
     *
     * @param $order Mage_Sales_Model_Order magento order object
     *
     * @return array basket details for heidelpay billSafe api call
     */
    public function getBasket($order)
    {
        $parameters =array();
        $items = $order->getAllVisibleItems();
        $itemCount = 0;

        if ($items) {
            foreach ($items as $item) {
                $itemCount++;
                $prefix = 'CRITERION.POS_' . sprintf('%02d', $itemCount);

                /** @var $item Mage_Sales_Model_Order_Item */
                $quantity = (int)$item->getQtyOrdered();
                $parameters[$prefix . '.POSITION'] = $itemCount;
                $parameters[$prefix . '.QUANTITY'] = $quantity;
                $parameters[$prefix . '.UNIT'] = 'Stk.'; // Liter oder so
                $parameters[$prefix . '.AMOUNT_UNIT_GROSS'] =
                    floor(bcmul($item->getPriceInclTax(), 100, 10));
                $parameters[$prefix . '.AMOUNT_GROSS'] =
                    floor(bcmul($item->getPriceInclTax() * $quantity, 100, 10));


                $parameters[$prefix . '.TEXT'] = $item->getName();
                $parameters[$prefix . '.COL1'] = 'SKU:' . $item->getSku();
                $parameters[$prefix . '.ARTICLE_NUMBER'] = $item->getProductId();
                $parameters[$prefix . '.PERCENT_VAT'] = sprintf('%1.2f', $item->getTaxPercent());
                $parameters[$prefix . '.ARTICLE_TYPE'] = 'goods';
            }
        }

        if ($this->_basketApiHelper->getShippingNetPrice($order) > 0) {
            $itemCount++;
            $prefix = 'CRITERION.POS_' . sprintf('%02d', $itemCount);
            $parameters[$prefix . '.POSITION'] = $itemCount;
            $parameters[$prefix . '.QUANTITY'] = '1';
            $parameters[$prefix . '.UNIT'] = 'Stk.'; // Liter oder so
            $parameters[$prefix . '.AMOUNT_UNIT_GROSS'] = floor(
                bcmul(
                    (($order->getShippingAmount() - $order->getShippingRefunded())
                        * (1 + $this->_basketApiHelper->getShippingTaxPercent($order) / 100)),
                    100, 10
                )
            );
            $parameters[$prefix . '.AMOUNT_GROSS'] = floor(
                bcmul(
                    (($order->getShippingAmount() - $order->getShippingRefunded())
                        * (1 + $this->_basketApiHelper->getShippingTaxPercent($order) / 100)),
                    100, 10
                )
            );

            $parameters[$prefix . '.TEXT'] = 'Shipping';
            $parameters[$prefix . '.ARTICLE_NUMBER'] = '0';
            $parameters[$prefix . '.PERCENT_VAT'] = $this->_basketApiHelper->getShippingTaxPercent($order);
            $parameters[$prefix . '.ARTICLE_TYPE'] = 'shipment';
        }

        if ($order->getDiscountAmount() < 0) {
            $itemCount++;
            $prefix = 'CRITERION.POS_' . sprintf('%02d', $itemCount);
            $parameters[$prefix . '.POSITION'] = $itemCount;
            $parameters[$prefix . '.QUANTITY'] = '1';
            $parameters[$prefix . '.UNIT'] = 'Stk.'; // Liter oder so
            $parameters[$prefix . '.AMOUNT_UNIT_GROSS'] = floor(bcmul($order->getDiscountAmount(), 100, 10));
            $parameters[$prefix . '.AMOUNT_GROSS'] = floor(bcmul($order->getDiscountAmount(), 100, 10));

            $parameters[$prefix . '.TEXT'] = 'Voucher';
            $parameters[$prefix . '.ARTICLE_NUMBER'] = '0';
            $parameters[$prefix . '.PERCENT_VAT'] = '0.00';
            $parameters[$prefix . '.ARTICLE_TYPE'] = 'voucher';
        }

        return $parameters;
    }
}
