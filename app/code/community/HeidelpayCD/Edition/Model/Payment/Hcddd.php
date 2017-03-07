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
class HeidelpayCD_Edition_Model_Payment_Hcddd extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcddd';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    // protected $_infoBlockType = 'hcd/info_debit';
    protected $_formBlockType = 'hcd/form_debit';
    
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
    
    public function isAvailable($quote = null)
    {
        $path = "payment/" . $this->_code . "/";
        $storeId = Mage::app()->getStore()->getId();
        $insurence = Mage::getStoreConfig($path . 'insurence', $storeId);
        
        // in case if insurence billing and shipping adress
        if ($insurence > 0) {
            $billing = $this->getQuote()->getBillingAddress();
            $shipping = $this->getQuote()->getShippingAddress();
            
            if (($billing->getFirstname() != $shipping->getFirstname()) or ($billing->getLastname() != $shipping->getLastname()) or ($billing->getStreet() != $shipping->getStreet()) or ($billing->getPostcode() != $shipping->getPostcode()) or ($billing->getCity() != $shipping->getCity()) or ($billing->getCountry() != $shipping->getCountry())) {
                $this->log('direct debit with insurence not allowed with diffrend adresses');
                return false;
            }
        }

        return parent::isAvailable($quote);
    }
    
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');
        
        //Mage::throwException(print_r($payment,1));
        
        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code.'_salut', $payment)) {
                $params['NAME.SALUTATION'] = (preg_match('/[A-z]{2}/', $payment[$this->_code.'_salut'])) ? $payment[$this->_code.'_salut'] : '';
            }
            
            if (array_key_exists($this->_code.'_dobday', $payment) &&
                array_key_exists($this->_code.'_dobmonth', $payment) &&
                array_key_exists($this->_code.'_dobyear', $payment)
                ) {
                $day    = (int)$payment[$this->_code.'_dobday'];
                $mounth = (int)$payment[$this->_code.'_dobmonth'];
                $year    = (int)$payment[$this->_code.'_dobyear'];
                
                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] = $year.'-'.sprintf("%02d", $mounth).'-'.sprintf("%02d", $day);
                } else {
                    Mage::throwException($this->_getHelper()->__('The minimum age is 18 years for this payment methode.'));
                }
            }
        
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }

            if (empty($payment[$this->_code.'_iban'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a iban or account'));
            }

            if (empty($payment[$this->_code.'_bic'])) {
                if (!preg_match('/^[A-Za-z]{2}/', $payment[$this->_code.'_iban'])) {
                    Mage::throwException($this->_getHelper()->__('Please specify a bank code'));
                }
            }
        
            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
                
            if (preg_match('#^[\d]#', $payment[$this->_code.'_iban'])) {
                $params['ACCOUNT.NUMBER'] = $payment[$this->_code.'_iban'];
            } else {
                $params['ACCOUNT.IBAN'] = $payment[$this->_code.'_iban'];
            }
            
            if (preg_match('#^[\d]#', $payment[$this->_code.'_bic'])) {
                $params['ACCOUNT.BANK'] = $payment[$this->_code.'_bic'];
                $params['ACCOUNT.COUNTRY'] = $this->getQuote()->getBillingAddress()->getCountry();
            } else {
                $params['ACCOUNT.BIC'] = $payment[$this->_code.'_bic'];
            }

            $this->saveCustomerData($params);
            
            return $this;
        }
        
        return $this;
    }
    
    public function showPaymentInfo($payment_data)
    {
        $load_snippet = $this->_getHelper()->__("Direct Debit Info Text");
        
        $repl = array(
                    '{AMOUNT}'                    => $payment_data['CLEARING_AMOUNT'],
                    '{CURRENCY}'                  => $payment_data['CLEARING_CURRENCY'],
                    '{Iban}'         => $payment_data['ACCOUNT_IBAN'],
                    '{Ident}'         => $payment_data['ACCOUNT_IDENTIFICATION'],
                    '{CreditorId}'   => $payment_data['IDENTIFICATION_CREDITOR_ID'],
                );
                
        $load_snippet= strtr($load_snippet, $repl);
                
        
        return $load_snippet;
    }
}
