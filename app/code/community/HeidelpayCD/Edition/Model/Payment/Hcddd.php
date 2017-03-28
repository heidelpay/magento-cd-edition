<?php
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcddd extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcddd';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_formBlockType = 'hcd/form_debit';
    /**
     * over write existing info block
     *
     * @var string
     */
    protected $_infoBlockType = 'hcd/info_directDebit';

    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
        
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');

        
        if (isset($payment['method']) and $payment['method'] == $this->_code) {
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
    
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Direct Debit Info Text");
        
        $repl = array(
                    '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
                    '{Iban}' => $paymentData['ACCOUNT_IBAN'],
                    '{Ident}' => $paymentData['ACCOUNT_IDENTIFICATION'],
                    '{CreditorId}' => $paymentData['IDENTIFICATION_CREDITOR_ID'],
                );
                
        return $loadSnippet= strtr($loadSnippet, $repl);
    }

    /**
     * @inheritdoc
     */
    public function chargeBack($order, $message = "")
    {
        $message = Mage::helper('hcd')->__('debit failed');
        return parent::chargeBack($order, $message);
    }
}
