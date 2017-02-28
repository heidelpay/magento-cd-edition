<?php

class HeidelpayCD_Edition_Model_System_Config_Source_Returnurl
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'basket', 'label'=>Mage::helper('hcd')->__('Basket')),
            array('value'=>'onepage', 'label'=>Mage::helper('hcd')->__('Onepage Checkout')),
            // array('value'=>'onestepcheckout', 'label'=>Mage::helper('hcd')->__('Onestep Checkout'))
        );
    }
}
