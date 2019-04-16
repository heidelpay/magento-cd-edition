<?php

/** @noinspection LongInheritanceChainInspection */
/**
 * BillSafe payment method
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * @copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
class HeidelpayCD_Edition_Model_Payment_Hcdbs extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /** @var $_basketApiHelper HeidelpayCD_Edition_Helper_BasketApi  */
    protected $_basketApiHelper;

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdbs constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdbs';
        $this->_canRefund = false;
        $this->_canRefundInvoicePartial = false;
        $this->_reportsShippingToHeidelpay = true;

        $this->_basketApiHelper = Mage::helper('hcd/basketApi');
    }

    /**
     * @inheritdoc
     */
    public function isAvailable($quote = null)
    {
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        if (($billing->getFirstname() !== $shipping->getFirstname()) ||
            ($billing->getLastname() !== $shipping->getLastname()) ||
            ($billing->getStreet() !== $shipping->getStreet()) ||
            ($billing->getPostcode() !== $shipping->getPostcode()) ||
            ($billing->getCity() !== $shipping->getCity()) ||
            ($billing->getCountry() !== $shipping->getCountry())
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
        $loadSnippet = $this->_getHelper()->__('BillSafe Info Text');

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
        /** @noinspection SuspiciousAssignmentsInspection */
        $message = 'BillSafe Id: ' . $data['CRITERION_BILLSAFE_REFERENCE'];

        return parent::processingTransaction($order, $data, $message);
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

        if ($this->getShippingNetPrice($order) > 0) {
            $itemCount++;
            $prefix = 'CRITERION.POS_' . sprintf('%02d', $itemCount);
            $parameters[$prefix . '.POSITION'] = $itemCount;
            $parameters[$prefix . '.QUANTITY'] = '1';
            $parameters[$prefix . '.UNIT'] = 'Stk.'; // Liter oder so
            $parameters[$prefix . '.AMOUNT_UNIT_GROSS'] = $this->getAmountGross($order);
            $parameters[$prefix . '.AMOUNT_GROSS'] = $this->getAmountGross($order);

            $parameters[$prefix . '.TEXT'] = 'Shipping';
            $parameters[$prefix . '.ARTICLE_NUMBER'] = '0';
            $parameters[$prefix . '.PERCENT_VAT'] = $this->getShippingTaxPercent($order);
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

    /**
     * @param $order
     *
     * @return float
     */
    private function getAmountGross($order)
    {
        return floor(
            bcmul(
                ($order->getShippingAmount() - $order->getShippingRefunded())
                * (1 + $this->getShippingTaxPercent($order) / 100),
                100, 10
            )
        );
    }

    //<editor-fold desc="Legacy Basket">

    /**
     * Calculate shipping net price
     *
     * @param $order Mage_Sales_Model_Order magento order object
     *
     * @return string shipping net price
     */
    public function getShippingNetPrice($order)
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
    public function getShippingTaxPercent($order)
    {
        $tax = ($order->getShippingTaxAmount() * 100) / $order->getShippingAmount();
        return $this->format(round($tax));
    }

    /**
     * function to format amount
     *
     * @param mixed $number
     *
     * @return string
     */
    public function format($number)
    {
        return number_format($number, 2, '.', '');
    }

    //</editor-fold>
}
