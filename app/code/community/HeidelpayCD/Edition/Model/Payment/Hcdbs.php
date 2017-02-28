<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method billsafe
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
 * @deprecated Payment method BillSafe is not longer available
 *
 */
class HeidelpayCD_Edition_Model_Payment_Hcdbs
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdbs';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    
    public function isAvailable($quote=null)
    {
        $billing  = $this->getQuote()->getBillingAddress();
        $shipping  = $this->getQuote()->getShippingAddress();
        
        if (($billing->getFirstname()     != $shipping->getFirstname())     or
                ($billing->getLastname()     != $shipping->getLastname())     or
                ($billing->getStreet()         != $shipping->getStreet())        or
                ($billing->getPostcode()     != $shipping->getPostcode())    or
                ($billing->getCity()             != $shipping->getCity())    or
                ($billing->getCountry()         != $shipping->getCountry())) {
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
                    '{CONNECTOR_ACCOUNT_HOLDER}'
                    => $paymentData['CRITERION_BILLSAFE_RECIPIENT'],
                    '{CONNECTOR_ACCOUNT_IBAN}'
                    => $paymentData['CRITERION_BILLSAFE_IBAN'],
                    '{CONNECTOR_ACCOUNT_BIC}'
                    => $paymentData['CRITERION_BILLSAFE_BIC'],
                    '{IDENTIFICATION_SHORTID}'
                    => $paymentData['CRITERION_BILLSAFE_REFERENCE'],
                    '{PERIOD}'
                    => $paymentData['CRITERION_BILLSAFE_PERIOD']
                );

        $loadSnippet= strtr($loadSnippet, $repl);
                
            
        return  $loadSnippet;
    }
}
