<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Invoice by Santander Class
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2017-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento-cd-edition
 *
 * @author Stephano Vogel
 *
 * @package heidelpay/magento-cd-edition/template/form/santander-invoice
 */
class HeidelpayCD_Edition_Model_Payment_Hcdivsan extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdivsan constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdivsan';
        $this->_formBlockType = 'hcd/form_santanderInvoice';
        $this->_canBasketApi = true;
        $this->_canReversal = true;
        $this->_sendsInvoiceMailComment = true;
        $this->_reportsShippingToHeidelpay = true;
    }

    /**
     * @inheritdoc
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    public function isAvailable($quote = null)
    {
        return false;

// --- Can be re-activated when santander will be free to use ---
//        if ($quote === null) {
//            return false;
//        }
//
//        if ($quote->getIsVirtual()) {
//            return false;
//        }
//
//        return parent::isAvailable($quote);
    }

    /**
     * @inheritdoc
     *
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPost('payment');

        // if the payment method code is not present in the request
        // or it is not equivalent to this class' payment code.
        if (!isset($this->_postPayload['method']) || $this->_postPayload['method'] !== $this->getCode()) {
            $this->log('Request payment method code does not match "hcdivsan".', 'WARNING');
            Mage::throwException($this->_getHelper()->__('Something went wrong. Please try again.'));
        }

        $advField = $this->getCode() . '_adv_optout';
        $privPolField = $this->getCode() . '_privpol_optin';

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
     * @param Mage_Sales_Model_Order $order magento order object
     * @param bool                   $isReg in case of registration
     *
     * @return array
     *
     * @throws Mage_Core_Exception
     */
    public function getUser($order, $isReg = false)
    {
        // get parent user information
        $user = parent::getUser($order, $isReg);

        // retrieve heidelpay customer data (which was saved in checkout)
        $billing = $order->getBillingAddress();
        $hcdCustomerData = $this->getCustomerData($this->getCode(), $billing->getCustomerId());

        if (isset($hcdCustomerData['payment_data']['CUSTOMER.OPTIN'])) {
            $user['CUSTOMER.OPTIN'] = $hcdCustomerData['payment_data']['CUSTOMER.OPTIN'];
        }

        if (isset($hcdCustomerData['payment_data']['CUSTOMER.OPTIN_2'])) {
            $user['CUSTOMER.OPTIN_2'] = $hcdCustomerData['payment_data']['CUSTOMER.OPTIN_2'];
        }

        /** @var $paymentHelper HeidelpayCD_Edition_Helper_Payment */
        $paymentHelper = Mage::helper('hcd/payment');

        // risk information to reduce the risk of rejection
        $user['RISKINFORMATION.CUSTOMERGUESTCHECKOUT'] = $user['CRITERION.GUEST']; // is already set by parent getUser()
        $user['RISKINFORMATION.CUSTOMERORDERCOUNT'] = $paymentHelper->getCustomerOrderCount($order);
        $user['RISKINFORMATION.CUSTOMERSINCE'] = $paymentHelper->getCustomerRegistrationDate($order);

        return $user;
    }
}