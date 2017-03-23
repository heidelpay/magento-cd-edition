<?php

/**
 * Invoice secured payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
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
class HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * payment code
     *
     * @var string payment code
     */
    protected $_code = 'hcdivsec';

    /**
     * send basket information to basket api
     *
     * @var bool send basket information to basket api
     */
    protected $_canBasketApi = true;

    /**
     * set checkout form block
     *
     * @var string checkout form block
     */
    protected $_formBlockType = 'hcd/form_invoiceSecured';

    /**
     * Over wright from block
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * is payment method available
     *
     * @param null $quote
     *
     * @return bool is payment method available
     */
    public function isAvailable($quote = null)
    {
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        /* billing and shipping address has to match */
        if (($billing->getFirstname() != $shipping->getFirstname()) or
            ($billing->getLastname() != $shipping->getLastname()) or
            ($billing->getStreet() != $shipping->getStreet()) or
            ($billing->getPostcode() != $shipping->getPostcode()) or
            ($billing->getCity() != $shipping->getCity()) or
            ($billing->getCountry() != $shipping->getCountry())
        ) {
            return false;
        }

        /* payment method is b2c only */
        if (!empty($billing->getCompany())) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Validate customer input on checkout
     *
     * @return $this
     */
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');


        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code . '_salutation', $payment)) {
                $params['NAME.SALUTATION'] =
                    (
                        $payment[$this->_code . '_salutation'] == 'MR' or
                        $payment[$this->_code . '_salutation'] == 'MRS'
                    )
                        ? $payment[$this->_code . '_salutation'] : '';
            }

            if (array_key_exists($this->_code . '_dobday', $payment) &&
                array_key_exists($this->_code . '_dobmonth', $payment) &&
                array_key_exists($this->_code . '_dobyear', $payment)
            ) {
                $day = (int)$payment[$this->_code . '_dobday'];
                $mounth = (int)$payment[$this->_code . '_dobmonth'];
                $year = (int)$payment[$this->_code . '_dobyear'];

                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] = $year . '-' . sprintf("%02d", $mounth) . '-' . sprintf("%02d", $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment methode.')
                    );
                }
            }


            $this->saveCustomerData($params);
        }

        return $this;
    }

    /**
     * Payment information for invoice mail
     *
     * @param array $paymentData transaction response
     *
     * @return string return payment information text
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Invoice Info Text");

        $replace = array(
            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CONNECTOR_ACCOUNT_BIC'],
            '{IDENTIFICATION_SHORTID}' => $paymentData['IDENTIFICATION_SHORTID'],
        );

        return strtr($loadSnippet, $replace);
    }

    /**
     * Handle transaction with means pending
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */

    public function pendingTransaction($order,$data,$message='')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] .' '.$message;


        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
        $order->setIsInProcess(true);
        $invoice->setIsPaid(false);
        $order->addStatusHistoryComment(
            Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'),
            false
        );
        $invoice->save();
        if ($this->_invoiceOrderEmail) {
            $code = $order->getPayment()->getMethodInstance()->getCode();
            if ($code == 'hcdiv' or $code == 'hcdivsec') {
                $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
                $invoiceMailComment = ($info === false) ? '' : '<h6>'
                    . $this->_getHelper()->__('payment information') . '</h6><p>' . $info . '</p>';
            }

            $invoice->sendEmail(true, $invoiceMailComment); // send invoice mail
        }


        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        $this->log('Set Transaction to Pending : ');
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusSuccess(false),
            $order->getPayment()->getMethodInstance()->getStatusSuccess(true),
            $message
        );

        $order->getPayment()->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            true,
            $message
        );

        return $order;
    }
}
