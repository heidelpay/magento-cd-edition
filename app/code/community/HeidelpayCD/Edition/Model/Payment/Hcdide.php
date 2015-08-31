<?php
class HeidelpayCD_Edition_Model_Payment_Hcdide extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	/**
	* unique internal payment method identifier
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'hcdide';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;
	
	protected $_formBlockType = 'hcd/form_ideal';
	
	public function getFormBlockType(){
		return $this->_formBlockType;
	}
	
	public function validate(){
		parent::validate();
		$payment = array();
		$params = array();
		$payment = Mage::app()->getRequest()->getPOST('payment');
		
		if($payment['method'] == $this->_code) {
		
			if(empty($payment[$this->_code.'_holder']))
			       Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
		
		$params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
			
		$params['ACCOUNT.BANKNAME'] = $payment[$this->_code.'_bank'];
		$params['ACCOUNT.COUNTRY'] = $this->getQuote()->getBillingAddress()->getCountry();
			
			
			$this->saveCustomerData($params);
			
            return $this;
        }
        
	return $this;
	}
	
}

