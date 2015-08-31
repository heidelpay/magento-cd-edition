<?php
class HeidelpayCD_Edition_Block_Info_Masterpass extends Mage_Payment_Block_Info
{
	/**
	 * Init default template for block
	 */
	protected function _construct()
	{
    parent::_construct();
        $this->setTemplate('hcd/info/masterpass.phtml');
	}
}