<?php

class HeidelpayCD_Edition_Model_Resource_Encryption extends Mage_Core_Model_Encryption {
	
	public function hash($data)    {
        return hash('sha256', $data);
    }
    
    public function getHash($string, $salt = false) {
    	
    	if ($salt === false) {
    		$salt = (string)Mage::getConfig()->getNode('global/crypt/key');
    	}
    	return $this->hash( $salt.(string)$string ) ;
    }
    
    public function validateHash($string, $hash) {
		return $this->getHash((string)$string) === $hash;
	}
}
