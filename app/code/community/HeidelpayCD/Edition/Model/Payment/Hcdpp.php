<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Prepayment payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdpp extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdpp constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdpp';
        $this->_infoBlockType = 'hcd/info_prepayment';
        $this->_showAdditionalPaymentInformation = true;
    }

    /**
     * Generates a customer message for the success page
     *
     * Will be used for prepayment and direct debit to show the customer
     * the billing information
     *
     * @param array $paymentData transaction details form heidelpay api
     *
     * @return bool| string  customer message for the success page
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__('Prepayment Info Text');
        
        $reply = array(
                    '{AMOUNT}'                    => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}'                  => $paymentData['CLEARING_CURRENCY'],
                    '{CONNECTOR_ACCOUNT_HOLDER}'  => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
                    '{CONNECTOR_ACCOUNT_IBAN}'    => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
                    '{IDENTIFICATION_SHORTID}'    => $paymentData['IDENTIFICATION_SHORTID'],
                );
                
        $loadSnippet= strtr($loadSnippet, $reply);
                
        return $loadSnippet;
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

        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
        /** @noinspection PhpUndefinedMethodInspection */
        $order->setIsInProcess(true);
        /** @noinspection PhpUndefinedMethodInspection */
        $invoice->setIsPaid(false);
        $order->addStatusHistoryComment(Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'));
        $invoice->save();
        if ($this->canInvoiceOrderEmail()) {
            $invoice->sendEmail(); // send invoice mail
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave->save();

        $this->log('Setting order status/state to pending and generate invoice.');
        /** @noinspection PhpUndefinedMethodInspection */
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusPending(false),
            $order->getPayment()->getMethodInstance()->getStatusPending(true),
            $message
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
     * @param Mage_Sales_Model_Order $order
     * @param HeidelpayCD_Edition_Model_Transaction $data
     * @param string $message order history message
     *
     * @return Mage_Sales_Model_Order
     * @throws \Exception
     * @throws \Mage_Core_Exception
     */
    public function processingTransaction($order, $data, $message = '')
    {

        /** @var  $paymentHelper HeidelpayCD_Edition_Helper_Payment */
        $paymentHelper = Mage::helper('hcd/payment');


        $message = ($message === '') ? 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] : $message;
        $totallyPaid = false;

        /** @noinspection PhpUndefinedMethodInspection */
        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(true);

        if ($order->getOrderCurrencyCode() === $data['PRESENTATION_CURRENCY'] &&
            $paymentHelper->format($order->getGrandTotal()) === $data['PRESENTATION_AMOUNT']
        ) {
            /** @noinspection PhpUndefinedMethodInspection */
            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusSuccess(false),
                $order->getPayment()->getMethodInstance()->getStatusSuccess(true),
                $message
            );
            $totallyPaid = true;
        } else {
            // in case rc is ack and amount is to low or currency miss match
            /** @noinspection PhpUndefinedMethodInspection */
            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(false),
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(true),
                $message
            );
        }

        // Set invoice to paid when the total amount matches
        if ($totallyPaid && $order->hasInvoices()) {

            /** @var Mage_Sales_Model_Resource_Order_Invoice_Collection $invoices */
            $invoices = $order->getInvoiceCollection();

            /** @var  $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($invoices as $invoice) {
                $this->log('Set invoice ' . (string)$invoice->getIncrementId() . ' to paid.');
                /** @noinspection PhpUndefinedMethodInspection */
                $invoice
                    ->capture()
                    ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                    ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                    ->setIsPaid(true)
                    // @codingStandardsIgnoreLine use of save in a loop
                    ->save();

                /** @var Mage_Core_Model_Resource_Transaction $transaction */
                $transaction = Mage::getModel('core/resource_transaction');
                $transactionSave = $transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
            }
        }

        // Set total paid and invoice to the connector amount
        $order->setTotalInvoiced($data['PRESENTATION_AMOUNT']);
        $order->setTotalPaid($data['PRESENTATION_AMOUNT']);


        $order->getPayment()->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            null,
            true,
            $message
        );

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setIsInProcess(true);

        return $order;
    }
}
