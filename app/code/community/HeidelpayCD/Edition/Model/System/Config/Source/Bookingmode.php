<?php

class HeidelpayCD_Edition_Model_System_Config_Source_Bookingmode
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'DB', 'label'=>Mage::helper('hcd')->__('Direct Booking')),
            array('value'=>'PA', 'label'=>Mage::helper('hcd')->__('Preauthorisation'))
        );
    }
}