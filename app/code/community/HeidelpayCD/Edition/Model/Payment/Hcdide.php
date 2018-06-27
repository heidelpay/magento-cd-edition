<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Ideal payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdide extends HeidelpayCD_Edition_Model_Payment_Abstract
{

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdide constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdide';
        $this->_canRefund = false;
        $this->_canRefundInvoicePartial = false;
        $this->_formBlockType = 'hcd/form_ideal';
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
        $params = array();
        $payment = Mage::app()->getRequest()->getPost('payment');
        
        if ($payment['method'] === $this->getCode()) {
            if (empty($payment[$this->getCode().'_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }
        
            $params['ACCOUNT.HOLDER'] = $payment[$this->getCode().'_holder'];
            $params['ACCOUNT.BANKNAME'] = $payment[$this->getCode().'_bank'];
            $params['ACCOUNT.COUNTRY'] = $this->getQuote()->getBillingAddress()->getCountry();

            $this->saveCustomerData($params);
            
            return $this;
        }
        
        return $this;
    }
}
