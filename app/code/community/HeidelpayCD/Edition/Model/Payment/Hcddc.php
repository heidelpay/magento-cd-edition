<?php
class HeidelpayCD_Edition_Model_Payment_Hcddc extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	/**
	* unique internal payment method identifier
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'hcddc';
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	
	public function isRecognation() {
		$path = "payment/".$this->_code."/";
		$storeId =  Mage::app()->getStore()->getId();
		return Mage::getStoreConfig($path.'recognition', $storeId);
		
	}

	public function	activRedirct() {
		$recognation = $this->isRecognation();
		if ($recognation > 0) return true;
		return false ;
	} 	
	
	protected $_formBlockType = 'hcd/form_creditcard';
	
	public function getFormBlockType(){
		return $this->_formBlockType;
	}
	
}
