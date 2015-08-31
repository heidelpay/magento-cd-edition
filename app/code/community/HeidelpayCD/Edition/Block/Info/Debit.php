<?php
class HeidelpayCD_Edition_Block_Info_Debit extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        /*
        $session = $this->getSession();
        $transport = new Varien_Object();
        $transport = parent::_prepareSpecificInformation($transport);
        $transport->addData(array(
            Mage::helper('hcd')->__('IBAN') =>  print_r($session,1)."48534958",
            Mage::helper('hcd')->__('BIC') => "48534958",
            Mage::helper('hcd')->__('Owner') => "123456"
        ));
        return $transport;
        */
    }
}