<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Direct debit payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcddd extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdpp constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcddd';
        $this->_canCapture = true;
        $this->_canCapturePartial = true;
        $this->_formBlockType = 'hcd/form_debit';
        $this->_infoBlockType = 'hcd/info_directDebit';
        $this->_showAdditionalPaymentInformation = true;
    }

    /**
     * Validate input data from checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_Abstract
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        parent::validate();
        $params = array();
        $payment = Mage::app()->getRequest()->getPost('payment');

        if (isset($payment['method']) && $payment['method'] === $this->_code) {
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }

            if (empty($payment[$this->_code.'_iban'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a iban or account'));
            }

            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
            $params['ACCOUNT.IBAN'] = $payment[$this->_code.'_iban'];

            $this->saveCustomerData($params);
        }
        
        return $this;
    }

    /**
     * Generates a customer message for the success page
     *
     * Will be used for prepayment and direct debit to show the customer
     * the billing information
     *
     * @param HeidelpayCD_Edition_Model_Transaction $paymentData transaction details form heidelpay api
     *
     * @return bool| string  customer message for the success page
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__('Direct Debit Info Text');
        
        $repl = array(
                    '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
                    '{Iban}' => $paymentData['ACCOUNT_IBAN'],
                    '{Ident}' => $paymentData['ACCOUNT_IDENTIFICATION'],
                    '{CreditorId}' => $paymentData['IDENTIFICATION_CREDITOR_ID'],
                );
                
        return strtr($loadSnippet, $repl);
    }

    /**
     * Handle charge back notices from heidelpay payment
     *
     * @param $order Mage_Sales_Model_Order
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function chargeBackTransaction($order, $message = '')
    {
        /** @noinspection SuspiciousAssignmentsInspection */
        $message = Mage::helper('hcd')->__('debit failed');
        return parent::chargeBackTransaction($order, $message);
    }
}
