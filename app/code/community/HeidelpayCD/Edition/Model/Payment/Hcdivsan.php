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

        $advField = $this->_code . '_adv_optout';
        $privPolField = $this->_code . '_privpol_optin';

        // Privacy Policy terms & conditions must be accepted
        if (!isset($this->_postPayload[$privPolField])) {
            $this->log($privPolField . ' is not set or checked!');
            Mage::throwException($this->_getHelper()->__('Please accept the terms and conditions of Santander.'));
        }

        // CONFIG.OPTIN = advertising agreement (optional)
        // CONFIG.OPTIN_2 = privacy policy agreement (required)
        $this->_validatedParameters['CONFIG.OPTIN'] = isset($this->_postPayload[$advField]) ? 'TRUE' : 'FALSE';
        $this->_validatedParameters['CONFIG.OPTIN_2'] = isset($this->_postPayload[$privPolField]) ? 'TRUE' : 'FALSE';

        // validate age and salutation in AbstractSecured Class, also save _validatedParameters array in database
        return parent::validate();
    }
}