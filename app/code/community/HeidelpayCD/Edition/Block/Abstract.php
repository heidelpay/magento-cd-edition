<?php
class HeidelpayCD_Edition_Block_Abstract extends Mage_Core_Block_Template
{
	public function getSession() {
		return Mage::getSingleton('core/session');
	}
	
	public function getCheckout()
		{
		return Mage::getSingleton('checkout/session');
	}
	
	public function getCurrentOrder() {
		$order = Mage::getModel('sales/order');
		$session = $this->getCheckout();
		$order->loadByIncrementId($session->getLastRealOrderId());
		
		return $order ;
	}
	
}