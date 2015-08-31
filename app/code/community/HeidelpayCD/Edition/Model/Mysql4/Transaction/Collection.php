<?php

class HeidelpayCD_Edition_Model_Mysql4_Transaction_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	    public function _construct()
        {
            $this->_init('hcd/transaction');
            parent::_construct();
        }
}

