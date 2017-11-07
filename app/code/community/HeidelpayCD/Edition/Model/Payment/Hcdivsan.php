<?php

/**
 * Class HeidelpayCD_Edition_Model_Payment_Hcdivsan
 */
class HeidelpayCD_Edition_Model_Payment_Hcdivsan extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * @var string
     */
    const CODE = 'hcdivsan';

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdivsan constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = self::CODE;
        $this->_formBlockType = 'hcd/form_santanderInvoice';
        $this->_canBasketApi = true;
        $this->_reportsShippingToHeidelpay = true;
    }

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

        // Privacy Policy terms & conditions must be accepted, so if frontend validation
        // fails, throw an exception here to cancel further processing in checkout.
        if (!isset($this->_postPayload[$privPolField])) {
            Mage::throwException($this->_getHelper()->__('Please accept the terms and conditions of Santander.'));
        }

        // CUSTOMER.OPTIN = advertising agreement (optional)
        // CUSTOMER.OPTIN_2 = privacy policy agreement (required)
        $this->_validatedParameters['CUSTOMER.OPTIN'] = isset($this->_postPayload[$advField]) ? 'TRUE' : 'FALSE';
        $this->_validatedParameters['CUSTOMER.OPTIN_2'] = isset($this->_postPayload[$privPolField]) ? 'TRUE' : 'FALSE';

        // validate age and salutation in AbstractSecured Class, also save _validatedParameters array in database
        return parent::validate();
    }

    /**
     * @inheritdoc
     */
    public function getUser($order, $isReg = false)
    {
        // get parent user information
        $user = parent::getUser($order, $isReg);

        // retrieve heidelpay customer data (which was saved in checkout)
        $billing = $order->getBillingAddress();
        $hcdCustomerData = $this->getCustomerData($this->_code, $billing->getCustomerId());

        if (isset($hcdCustomerData['payment_data']['CUSTOMER.OPTIN'])) {
            $user['CUSTOMER.OPTIN'] = $hcdCustomerData['payment_data']['CUSTOMER.OPTIN'];
        }

        if (isset($hcdCustomerData['payment_data']['CUSTOMER.OPTIN_2'])) {
            $user['CUSTOMER.OPTIN_2'] = $hcdCustomerData['payment_data']['CUSTOMER.OPTIN_2'];
        }

        /** @var  $paymentHelper HeidelpayCD_Edition_Helper_Payment */
        $paymentHelper = Mage::helper('hcd/payment');

        // risk information to reduce the risk of rejection
        $user['RISKINFORMATION.CUSTOMERGUESTCHECKOUT'] = $user['CRITERION.GUEST']; // is already set by parent getUser()
        $user['RISKINFORMATION.CUSTOMERORDERCOUNT'] = $paymentHelper->getCustomerOrderCount(
            $order->getCustomerId(),
            $order->getCustomerIsGuest() ? true : false,
            $order->getCustomerEmail()
        );
        $user['RISKINFORMATION.CUSTOMERSINCE'] = $paymentHelper->getCustomerRegistrationDate(
            $order->getCustomerId(),
            $order->getCustomerIsGuest() ? true : false
        );

        return $user;
    }
}