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
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
class HeidelpayCD_Edition_Model_Payment_Hcdiv extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdiv constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdiv';
        $this->_sendInvoiceMailComment = true;
        $this->_infoBlockType = 'hcd/info_invoice';
    }

    /**
     * Payment information for invoice mail
     *
     * @param array $paymentData transaction response
     *
     * @return string return payment information text
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__('Invoice Info Text');

        $reply = array(
            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CONNECTOR_ACCOUNT_BIC'],
            '{IDENTIFICATION_SHORTID}' => $paymentData['IDENTIFICATION_SHORTID'],
        );

        return strtr($loadSnippet, $reply);
    }

    /**
     * Handle transaction with means processing
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function processingTransaction($order, $data, $message='')
    {
        $message = Mage::helper('hcd')->__('received amount ')
            . $data['PRESENTATION_AMOUNT'] . ' ' . $data['PRESENTATION_CURRENCY'] . ' ' . $message;

        return parent::processingTransaction($order, $data, $message);
    }
}
