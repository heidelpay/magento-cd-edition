<?php
class HeidelpayCD_Edition_Block_Index extends HeidelpayCD_Edition_Block_Abstract
{
	
	function getHcdHolder() {
		$order = $this->getCurrentOrder();
		return $order->getBillingAddress()->getFirstname().' '.$order->getBillingAddress()->getLastname();
	}
	
}