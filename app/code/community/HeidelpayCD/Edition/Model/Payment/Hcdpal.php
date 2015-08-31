<?php
class HeidelpayCD_Edition_Model_Payment_Hcdpal extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	/**
	* unique internal payment method identifier
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'hcdpal';
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	
	
	/*
	 * PayPal seller protection, need shipping adress instead of billing (PAYPAL REV 20141215) 
	 */
	public function getUser($order, $isReg=false) {
		
		$user = array();
		
		$user = parent::getUser($order, $isReg);
		$adress	= ($order->getShippingAddress() == false) ? $order->getBillingAddress()  : $order->getShippingAddress() ;
		$email = ($adress->getEmail()) ? $adress->getEmail() : $order->getCustomerEmail();
		
		
		$user['IDENTIFICATION.SHOPPERID'] 	= $adress->getCustomerId();
		if ($adress->getCompany() == true) $user['NAME.COMPANY']	= trim($adress->getCompany());
		$user['NAME.GIVEN']			= trim($adress->getFirstname());
		$user['NAME.FAMILY']		= trim($adress->getLastname());
		$user['ADDRESS.STREET']		= $adress->getStreet1()." ".$adress->getStreet2();
		$user['ADDRESS.ZIP']		= $adress->getPostcode();
		$user['ADDRESS.CITY']		= $adress->getCity();
		$user['ADDRESS.COUNTRY']	= $adress->getCountry();
		$user['CONTACT.EMAIL']		= $email;
		
		return $user;	
	}
	
}

