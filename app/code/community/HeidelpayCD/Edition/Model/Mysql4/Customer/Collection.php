<?php

class HeidelpayCD_Edition_Model_Mysql4_Customer_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	    public function _construct()
        {
            $this->_init('hcd/customer');
            parent::_construct();
        }
}

