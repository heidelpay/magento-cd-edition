<?php

/** @noinspection LongInheritanceChainInspection */
/**
 * Invoice unsecured payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdiv extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdiv constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdiv';
        $this->_canReversal = true;
        $this->_sendsInvoiceMailComment = true;
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
     * Handle transaction with means pending
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     * @throws \Mage_Core_Exception
     */
    public function pendingTransaction($order, $data, $message = '')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] . ' ' . $message;

        /** @noinspection PhpUndefinedMethodInspection */
        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(false);

        /** @var Mage_Sales_Model_Service_Order $salesOrder */
        $salesOrder = Mage::getModel('sales/service_order', $order);

        /** @var Mage_Sales_Model_Convert_Order $convertOrder */
        $convertOrder = Mage::getModel('hcd/convert_order');
        $invoice = $salesOrder->setConvertor($convertOrder)->prepareInvoice();
        $invoice->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setIsInProcess(true);

        /** @noinspection PhpUndefinedMethodInspection */
        $invoice->setIsPaid(false);
        $order->addStatusHistoryComment(Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'));
        $invoice->save();

        // send invoice email if payment method is configured to do so
        if ($this->canInvoiceOrderEmail() && $this->isSendingInvoiceAutomatically($data)) {
            $invoiceMailComment = '';
            if ($this->isSendingInvoiceMailComment()) {
                /** @noinspection PhpUndefinedMethodInspection */
                $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
                $invoiceMailComment = ($info === false) ? '' : '<h3>'
                    . $this->_getHelper()->__('payment information') . '</h3><p>' . $info . '</p>';
            }

            $this->log('Sending invoice email for order #' . $order->getRealOrderId() . '...');
            $invoice->sendEmail(true, $invoiceMailComment);
        }


        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave->save();

        $this->log('Setting order status/state to processed and generate invoice.');

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusSuccess(),
            $order->getPayment()->getMethodInstance()->getStatusSuccess(true)
        );

        $order->getPayment()->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            true,
            $message
        );

        return $order;
    }

    /**
     * Handle transaction with means processing
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     * @throws \Mage_Core_Exception
     */
    public function processingTransaction($order, $data, $message = '')
    {
        /** @var HeidelpayCD_Edition_Helper_InvoiceHelper $invoiceHelper */
        $invoiceHelper = Mage::helper('hcd/InvoiceHelper');
        return $invoiceHelper->handleInvoicePayment($order, $data, $message);
    }
}
