<?php
/**
 * EPS payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdeps extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdeps';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    
    protected $_formBlockType = 'hcd/form_eps';
    
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
        
        if ($payment['method'] == $this->_code) {
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }
        
            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
            
            $params['ACCOUNT.BANKNAME'] = $payment[$this->_code.'_bank'];
            $params['ACCOUNT.COUNTRY'] = $this->getQuote()->getBillingAddress()->getCountry();
            
            
            $this->saveCustomerData($params);
            
            return $this;
        }
        
        return $this;
    }
}
