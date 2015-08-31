<?php
class HeidelpayCD_Edition_Model_Payment_Hcdpf extends HeidelpayCD_Edition_Model_Payment_Abstract
{
	protected $_code = 'hcdpf';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;
	protected $_formBlockType = 'hcd/form_postfinance';
	
	public function getFormBlockType(){
		return $this->_formBlockType;
	}
	
	public function isAvailable($quote=null) {
		$currency_code=$this->getQuote()->getQuoteCurrencyCode();
		if (!empty($currency_code) && $currency_code != 'CHF') return false;
		return parent::isAvailable($quote);
	}
	
	public function validate(){
		parent::validate();
		$payment = Mage::app()->getRequest()->getPOST('payment');
		
		
		if(empty($payment[$this->_code.'_pf'])) {
            $errorCode = 'invalid_data';
            $errorMsg = $this->_getHelper()->__('No Postfinance method selected');
            Mage::throwException($errorMsg);
            return $this;
        }
        
        $this->saveCustomerData(array('ACCOUNT.BRAND' => $payment[$this->_code.'_pf']));

	return $this;
	}
}