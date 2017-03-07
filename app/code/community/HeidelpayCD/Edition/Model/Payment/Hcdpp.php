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
    
    public function showPaymentInfo($payment_data)
    {
        $load_snippet = $this->_getHelper()->__("Prepayment Info Text");
        
        $repl = array(
                    '{AMOUNT}'                    => $payment_data['CLEARING_AMOUNT'],
                    '{CURRENCY}'                  => $payment_data['CLEARING_CURRENCY'],
                    '{CONNECTOR_ACCOUNT_HOLDER}'  => $payment_data['CONNECTOR_ACCOUNT_HOLDER'],
                    '{CONNECTOR_ACCOUNT_IBAN}'    => $payment_data['CONNECTOR_ACCOUNT_IBAN'],
                    '{IDENTIFICATION_SHORTID}'    => $payment_data['IDENTIFICATION_SHORTID'],
                );
                
        $load_snippet= strtr($load_snippet, $repl);
                
        return $load_snippet;
    }
}
