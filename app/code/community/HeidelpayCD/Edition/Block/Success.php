<?php
class HeidelpayCD_Edition_Block_Success extends HeidelpayCD_Edition_Block_Abstract
{
	function showPaymentInfo() {
		$return = array();
		$order = $this->getCurrentOrder();
		$session = $this->getCheckout();
		
		$info = ($session->getHcdPaymentInfo() !== false) ? $session->getHcdPaymentInfo() : false;
		
		if(!empty($info)) {
			
			$return['Title'] = 	$order->getPayment()->getMethodInstance()->getTitle();	
			$return['Message'] = $session->getHcdPaymentInfo();
			
			$session->unsHcdPaymentInfo();
			
			return $return;
		}
		
		return false;
		
	}
}