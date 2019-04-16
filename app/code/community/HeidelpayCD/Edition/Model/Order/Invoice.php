<?php
/**
 * Created by PhpStorm.
 * User: Simon.Gabriel
 * Date: 08.11.2017
 * Time: 11:56
 */
class HeidelpayCD_Edition_Model_Order_Invoice extends Mage_Sales_Model_Order_Invoice
{
    protected function _getEmails($configPath)
    {
        $emails = parent::_getEmails($configPath);

        $order = $this->_order;
        if ($order->getPayment()->getMethodInstance() instanceof HeidelpayCD_Edition_Model_Payment_Hcdivpol) {
            $emails[] = Mage::getStoreConfig(
                'payment/hcdivpol/provideremailaddress',
                $order->getStoreId()
            );
        }

        return $emails;
    }
}
