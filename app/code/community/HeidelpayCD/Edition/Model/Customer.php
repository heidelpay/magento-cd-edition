<?php

class HeidelpayCD_Edition_Model_Customer extends Mage_Core_Model_Abstract
{
	    public function _construct()
        {
            $this->_init('hcd/customer');
            parent::_construct();
        }
       
        
}
