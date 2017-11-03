<?php
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdivpol extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdivpol';

    /**
     * @var string checkout information and form
     */
    protected $_formBlockType = 'hcd/form_invoicePayolution';

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

        return parent::isAvailable($quote);
    }




//    /**
//     * over write existing info block
//     *
//     * @var string
//     */
//    protected $_infoBlockType = 'hcd/info_invoice';

//    /**
//     * Payment information for invoice mail
//     *
//     * @param array $paymentData transaction response
//     *
//     * @return string return payment information text
//     */
//    public function showPaymentInfo($paymentData)
//    {
//        /** @var HeidelpayCD_Edition_Model_Payment_Hcdivpol $loadSnippet */
//        $loadSnippet = $this->_getHelper()->__('Invoice Info Text');
//
//        $repl = array(
//            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
//            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
//            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
//            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
//            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CONNECTOR_ACCOUNT_BIC'],
//            '{IDENTIFICATION_SHORTID}' => $paymentData['IDENTIFICATION_SHORTID'],
//        );
//
//        return strtr($loadSnippet, $repl);
//    }
//
//    /**
//     * @inheritdoc
//     */
//    public function processingTransaction($order, $data, $message='')
//    {
//        $message = Mage::helper('hcd')->__('received amount ')
//            . $data['PRESENTATION_AMOUNT'] . ' ' . $data['PRESENTATION_CURRENCY'] . ' ' . $message;
//        parent::processingTransaction($order, $data, $message);
//    }
}
