<?php
class HeidelpayCD_Edition_Model_Payment_Hcdmk extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	protected $_code = 'hcdmk';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;
	
	public function isAvailable($quote=null) {
		$currency_code=$this->getQuote()->getQuoteCurrencyCode();
		if (!empty($currency_code) && $currency_code != 'TRY') return false;
		return parent::isAvailable($quote);
	}
	

}

