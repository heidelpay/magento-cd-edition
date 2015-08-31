<?php
class HeidelpayCD_Edition_Model_Payment_Hcdsu extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	/**
	* unique internal payment method identifier
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'hcdsu';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;
	
}

