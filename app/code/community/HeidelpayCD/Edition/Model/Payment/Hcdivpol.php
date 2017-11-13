<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Invoice unsecured payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Simon Gabriel
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
class HeidelpayCD_Edition_Model_Payment_Hcdivpol extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdivpol constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdivpol';
        $this->_formBlockType = 'hcd/form_invoicePayolution';
        $this->_canBasketApi = true;
        $this->_canAuthorize = true;
        $this->_canRefund = true;
        $this->_sendInvoiceMailComment = true;
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
        // check shipping address is same as before
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        /* billing and shipping address has to match */
        if (($billing->getFirstname() !== $shipping->getFirstname()) ||
            ($billing->getLastname() !== $shipping->getLastname()) ||
            ($billing->getStreet() !== $shipping->getStreet()) ||
            ($billing->getPostcode() !== $shipping->getPostcode()) ||
            ($billing->getCity() !== $shipping->getCity()) ||
            ($billing->getCountry() !== $shipping->getCountry())
        ) {
            return false;
        }

        // prohibit payment method if the customer has already been rejected in the current session
        /** @noinspection PhpUndefinedMethodInspection */
        if ($this->getCheckout()->getPayolutionCustomerRejected()) {
            return false;
        }

        return HeidelpayCD_Edition_Model_Payment_Abstract::isAvailable($quote);
    }

    /**
     * Customer parameter for heidelpay api call
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $isReg in case of registration
     *
     * @return array
     *
     */
    public function getUser($order, $isReg = false)
    {
        $user = parent::getUser($order, $isReg);

        $customerId = $order->getCustomerId();

        /** @var HeidelpayCD_Edition_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);

        $customerSinceTimestamp = null;

        // is registered customer
        if ($customerId !== 0) {
            /** @noinspection PhpUndefinedMethodInspection */
            $customerSinceTimestamp = $customer->getCreatedAtTimestamp();
        }

        /** @var Mage_Core_Model_Date $mageCoreModelAbstract */
        $mageCoreModelAbstract = Mage::getSingleton('core/date');
        $user['RISKINFORMATION.CUSTOMERSINCE'] =
        $mageCoreModelAbstract =
            $mageCoreModelAbstract->gmtDate('Y-m-d', $customerSinceTimestamp);

        return $user;
    }

    /**
     * Validate customer input on checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPost('payment');
        return parent::validate();
    }
}
