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
    protected $_code = 'hcdbs';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;

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

    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("BillSafe Info Text");

        $repl = array(
            '{LEGALNOTE}' => $paymentData['CRITERION_BILLSAFE_LEGALNOTE'],
            '{AMOUNT}' => $paymentData['CRITERION_BILLSAFE_AMOUNT'],
            '{CURRENCY}' => $paymentData['CRITERION_BILLSAFE_CURRENCY'],
            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CRITERION_BILLSAFE_RECIPIENT'],
            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CRITERION_BILLSAFE_IBAN'],
            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CRITERION_BILLSAFE_BIC'],
            '{IDENTIFICATION_SHORTID}' => $paymentData['CRITERION_BILLSAFE_REFERENCE'],
            '{PERIOD}' => $paymentData['CRITERION_BILLSAFE_PERIOD']
        );

        $loadSnippet = strtr($loadSnippet, $repl);


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
        $items = $order->getAllVisibleItems();

        if ($items) {
            $i = 0;
            foreach ($items as $item) {
                $i++;
                $prefix = 'CRITERION.POS_' . sprintf('%02d', $i);

                /** @var $item Mage_Sales_Model_Order_Item */
                $quantity = (int)$item->getQtyOrdered();
                $parameters[$prefix . '.POSITION'] = $i;
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

        if ($this->getShippingNetPrice($order) > 0) {
            $i++;
            $prefix = 'CRITERION.POS_' . sprintf('%02d', $i);
            $parameters[$prefix . '.POSITION'] = $i;
            $parameters[$prefix . '.QUANTITY'] = '1';
            $parameters[$prefix . '.UNIT'] = 'Stk.'; // Liter oder so
            $parameters[$prefix . '.AMOUNT_UNIT_GROSS'] = floor(
                bcmul(
                    (($order->getShippingAmount() - $order->getShippingRefunded())
                        * (1 + $this->getShippingTaxPercent($order) / 100)),
                    100, 10
                )
            );
            $parameters[$prefix . '.AMOUNT_GROSS'] = floor(
                bcmul(
                    (($order->getShippingAmount() - $order->getShippingRefunded())
                        * (1 + $this->getShippingTaxPercent($order) / 100)),
                    100, 10
                )
            );

            $parameters[$prefix . '.TEXT'] = 'Shipping';
            $parameters[$prefix . '.ARTICLE_NUMBER'] = '0';
            $parameters[$prefix . '.PERCENT_VAT'] = $this->getShippingTaxPercent($order);
            $parameters[$prefix . '.ARTICLE_TYPE'] = 'shipment';
        }

        if ($order->getDiscountAmount() < 0) {
            $i++;
            $prefix = 'CRITERION.POS_' . sprintf('%02d', $i);
            $parameters[$prefix . '.POSITION'] = $i;
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

    /**
     * Calculate shipping net price
     *
     * @param $order Mage_Sales_Model_Order magento order object
     *
     * @return string shipping net price
     */
    protected function getShippingNetPrice($order)
    {
        $shippingTax = $order->getShippingTaxAmount();
        $price = $order->getShippingInclTax() - $shippingTax;
        $price -= $order->getShippingRefunded();
        $price -= $order->getShippingCanceled();
        return $price;
    }

    /**
     * Calculate shipping tax in percent for BillSafe
     *
     * @param $order Mage_Sales_Model_Order magentp order object
     *
     * @return string shipping tex in percent
     */
    protected function getShippingTaxPercent($order)
    {
        $tax = ($order->getShippingTaxAmount() * 100) / $order->getShippingAmount();
        return Mage::helper('hcd/payment')->format(round($tax));
    }
}
