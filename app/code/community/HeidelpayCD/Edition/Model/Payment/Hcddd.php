<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method direct debit
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH.
 * All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento2
 *
 * @author Jens Richter
 *
 * @package heidelpay
 * @subpackage magento
 * @category magento
 *
 */
class HeidelpayCD_Edition_Model_Payment_Hcddd
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcddd';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
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
            
            if (($billing->getFirstname() != $shipping->getFirstname()) or
                ($billing->getLastname() != $shipping->getLastname()) or
                ($billing->getStreet() != $shipping->getStreet()) or
                ($billing->getPostcode() != $shipping->getPostcode())
                or ($billing->getCity() != $shipping->getCity())
                or ($billing->getCountry() != $shipping->getCountry())) {
                $this->log(
                    'direct debit with insurence not allowed with diffrend adresses'
                );
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
        
        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code.'_salut', $payment)) {
                $params['NAME.SALUTATION'] = (
                    preg_match('/[A-z]{2}/', $payment[$this->_code.'_salut'])
                )
                    ? $payment[$this->_code.'_salut'] : '';
            }
            
            if (array_key_exists($this->_code.'_dobday', $payment) &&
                array_key_exists($this->_code.'_dobmonth', $payment) &&
                array_key_exists($this->_code.'_dobyear', $payment)
                ) {
                $day     = (int)$payment[$this->_code.'_dobday'];
                $mounth = (int)$payment[$this->_code.'_dobmonth'];
                $year     = (int)$payment[$this->_code.'_dobyear'];
                
                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] =
                        $year.'-'.sprintf("%02d", $mounth).
                        '-'.sprintf("%02d", $day);
                } else {
                    Mage::throwException(
                        $this
                        ->_getHelper()
                        ->__(
                            'The minimum age is 18 years for this payment methode.'
                        )
                    );
                }
            }
        
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException(
                    $this->_getHelper()->__('Please specify a account holder')
                );
            }

            if (empty($payment[$this->_code.'_iban'])) {
                Mage::throwException(
                    $this->_getHelper()->__('Please specify a iban or account')
                );
            }

            if (empty($payment[$this->_code.'_bic'])) {
                if (!preg_match('/^[A-Za-z]{2}/', $payment[$this->_code.'_iban'])) {
                    Mage::throwException(
                        $this->_getHelper()
                        ->__('Please specify a bank code')
                    );
                }
            }

            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
                
            $params['ACCOUNT.IBAN'] = $payment[$this->_code.'_iban'];

            $this->saveCustomerData($params);
        };
        
        return $this;
    }
    
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Direct Debit Info Text");
        
        $repl = array(
                    '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
                    '{Iban}' => $paymentData['ACCOUNT_IBAN'],
                    '{Bic}'=> $paymentData['ACCOUNT_BIC'],
                    '{Ident}' => $paymentData['ACCOUNT_IDENTIFICATION'],
                    '{CreditorId}' => $paymentData['IDENTIFICATION_CREDITOR_ID'],
                );

        $loadSnippet= strtr($loadSnippet, $repl);
                
        
        return $loadSnippet;
    }
}
