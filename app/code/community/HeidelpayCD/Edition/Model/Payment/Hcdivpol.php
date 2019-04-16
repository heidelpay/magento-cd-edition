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
 * @author Simon Gabriel
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
        $this->_canBasketApi = true;
        $this->_canAuthorize = true;
        $this->_canReversal = true;
        $this->_sendsInvoiceMailComment = true;
        $this->_reportsShippingToHeidelpay = true;
        $this->_allowsBusinessToBusiness = false;
        $this->_formBlockType = 'hcd/form_payolutionInvoice';
    }

    /**
     * Customer parameter for heidelpay api call
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $isReg in case of registration
     *
     * @return array
     *
     * @throws \Mage_Core_Exception
     */
    public function getUser($order, $isReg = false)
    {
        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');
        $user = parent::getUser($order, $isReg);

        $user['RISKINFORMATION.CUSTOMERSINCE'] = $paymentHelper->getCustomerRegistrationDate($order);
        $user['RISKINFORMATION.CUSTOMERGUESTCHECKOUT'] = $paymentHelper->getCustomerIsGuest($order) ? 'TRUE' : 'FALSE';
        $user['RISKINFORMATION.CUSTOMERORDERCOUNT'] = $paymentHelper->getCustomerOrderCount($order);

        return $user;
    }

    /**
     * Validate customer input on checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
     *
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPost('payment');

        /** @noinspection NotOptimalIfConditionsInspection */
        if (array($this->_postPayload['method']) && $this->_postPayload['method'] === $this->getCode() &&
            array_key_exists($this->getCode() . '_telephone', $this->_postPayload)) {
            if (!empty($this->_postPayload[$this->getCode() . '_telephone'])) {
                $this->_validatedParameters['CONTACT.PHONE'] = $this->_postPayload[$this->getCode() . '_telephone'];
            } else {
                Mage::throwException(
                    $this->_getHelper()
                        ->__(
                            'Please enter a valid phone number e.g. +49 123 456-1222. Allowed symbols ' .
                            'are +, /, -, (, ) and whitespace.'
                        )
                );
            }
        }

        return parent::validate();
    }
}
