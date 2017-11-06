<?php

/**
 * Class HeidelpayCD_Edition_Model_Payment_Hcdivsan
 */
class HeidelpayCD_Edition_Model_Payment_Hcdivsan extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * @var string
     */
    protected $_code = 'hcdivsan';

    /**
     * @var string
     */
    protected $_formBlockType = 'hcd/form_santanderInvoice';

    /**
     * @inheritdoc
     *
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPOST('payment');

        // if the payment method code is not present in the request
        // or it is not equivalent to this class' payment code.
        if (!isset($this->_postPayload['method']) || $this->_postPayload['method'] !== $this->_code) {
            $this->log('Request payment method code does not match "hcdivsan".', 'WARNING');
            Mage::throwException($this->_getHelper()->__('Something went wrong. Please try again.'));
        }

        $this->log('hcdivsan post payload: ' . print_r($this->_postPayload, true));

//        if ($this->_postPayload[$this->_code . '-privpol-optin'] === null) {
//            Mage::throwException($this->_getHelper()->__('Please accept the terms and conditions of Santander.'));
//        }

        // validate age and salutation in AbstractSecured Class
        return parent::validate();
    }
}