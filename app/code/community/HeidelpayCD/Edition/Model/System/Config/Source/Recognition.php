

<?php

class HeidelpayCD_Edition_Model_System_Config_Source_Recognition
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'0', 'label'=>Mage::helper('hcd')->__('no recognition')),
            array('value'=>'1', 'label'=>Mage::helper('hcd')->__('only if shippping adress is unchanged')),
            array('value'=>'2', 'label'=>Mage::helper('hcd')->__('always'))
        );
    }
}
