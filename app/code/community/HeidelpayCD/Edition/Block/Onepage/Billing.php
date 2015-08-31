<?php

class HeidelpayCD_Edition_Block_Onepage_Billing extends Mage_Checkout_Block_Onepage_Billing {
	
	public function getAddress() {
		$HcdWallet = Mage::getSingleton('checkout/session')->getHcdWallet();
		if (!empty($HcdWallet)){
		
		
		$wallet = Mage::getSingleton('checkout/session')->getHcdWallet();
		$this->_address = Mage::getModel('sales/quote_address')->setAddressType(
			Mage_Sales_Model_Quote_Address::TYPE_BILLING)
			->setStoreId(Mage::app()->getStore()->getId())	
			->setFirstname($wallet['adress'] ['firstname'])
			->setLastname($wallet['adress'] ['lastname'])
			->setEmail($wallet['adress'] ['email'])
			->setSuffix((''))
			->setCompany('')
			->setStreet(array(
				'0' => $wallet['adress'] ['street'][0],
				'1' => $wallet['adress'] ['street'][1]
			))
			->setCity($wallet['adress'] ['city'])
			->setPostcode($wallet['adress'] ['postcode'])
			->setCountry_id($wallet['adress'] ['country_id'])
			->setRegion($wallet['adress'] ['region'])
			->setRegion_id((string)$wallet['adress'] ['region_id'])
			->setTelephone($wallet['adress'] ['telephone'])
			->setFax();
			
		return $this->_address;
		} else 
			return parent::getAddress();
	}
	
	
}
