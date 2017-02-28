<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method Postfinance
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
class HeidelpayCD_Edition_Model_Payment_Hcdpf
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdpf';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_formBlockType = 'hcd/form_postfinance';
    
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
    
    public function isAvailable($quote=null)
    {
        $currencyCode=$this->getQuote()->getQuoteCurrencyCode();
        if (!empty($currencyCode) && $currencyCode != 'CHF') {
            return false;
        }

        return parent::isAvailable($quote);
    }
    
    public function validate()
    {
        parent::validate();
        $payment = Mage::app()->getRequest()->getPOST('payment');
        
        
        if (empty($payment[$this->_code.'_pf'])) {
            $errorCode = 'invalid_data';
            $errorMsg = $this->_getHelper()->__('No Postfinance method selected');
            Mage::throwException($errorMsg);
            return $this;
        }
        
        $this->saveCustomerData(
            array('ACCOUNT.BRAND' => $payment[$this->_code.'_pf'])
        );

        return $this;
    }
}
