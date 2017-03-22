<?php
/**
 * Prepayment payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
class HeidelpayCD_Edition_Model_Payment_Hcdpp extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdpp';
    
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Prepayment Info Text");
        
        $repl = array(
                    '{AMOUNT}'                    => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}'                  => $paymentData['CLEARING_CURRENCY'],
                    '{CONNECTOR_ACCOUNT_HOLDER}'  => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
                    '{CONNECTOR_ACCOUNT_IBAN}'    => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
                    '{IDENTIFICATION_SHORTID}'    => $paymentData['IDENTIFICATION_SHORTID'],
                );
                
        $loadSnippet= strtr($loadSnippet, $repl);
                
        return $loadSnippet;
    }

    /**
     * @inheritdoc
     */
    public function processingTransaction($order, $data, $message='')
    {
        $message = Mage::helper('hcd')->__('received amount ')
            . $data['PRESENTATION_AMOUNT'] . ' ' . $data['PRESENTATION_CURRENCY'] . ' ' . $message;
        parent::processingTransaction($order, $data, $message);
    }
}
