<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Postfinance payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
class HeidelpayCD_Edition_Model_Payment_Hcdpf extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdpf constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdpf';
        $this->_canRefund = false;
        $this->_canRefundInvoicePartial = false;
        $this->_formBlockType = 'hcd/form_postfinance';
    }

    /**
     * Deactivate payment method in case of wrong currency or other credentials
     *
     * @param Mage_Quote
     * @param null|mixed $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $currencyCode=$this->getQuote()->getQuoteCurrencyCode();
        if (!empty($currencyCode) && $currencyCode !== 'CHF') {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Validate input data from checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_Abstract
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        parent::validate();
        $payment = Mage::app()->getRequest()->getPost('payment');
        
        
        if (empty($payment[$this->getCode().'_pf'])) {
            $errorMsg = $this->_getHelper()->__('No Postfinance method selected');
            Mage::throwException($errorMsg);
            return $this;
        }
        
        $this->saveCustomerData(array('ACCOUNT.BRAND' => $payment[$this->getCode().'_pf']));

        return $this;
    }
}
