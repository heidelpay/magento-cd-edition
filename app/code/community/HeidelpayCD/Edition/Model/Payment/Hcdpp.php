<?php
/**
 * Prepayment payment method
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdpp extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdpp';

    protected $_infoBlockType = 'hcd/info_prepayment';
    
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Prepayment Info Text");
        
        $repl = array(
                    '{AMOUNT}'                    => $paymentData['CLEARING_AMOUNT'],
                    '{CURRENCY}'                  => $paymentData['CLEARING_CURRENCY'],
                    '{CONNECTOR_ACCOUNT_HOLDER}'  => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
                    '{CONNECTOR_ACCOUNT_IBAN}'    => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
                    '{IDENTIFICATION_SHORTID}'    => $paymentData['IDENTIFICATION_SHORTID'],
                );
                
        $loadSnippet= strtr($loadSnippet, $repl);
                
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
     */
    public function pendingTransaction($order, $data, $message = '')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] . ' ' . $message;

        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(false);

        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
        $order->setIsInProcess(true);
        $invoice->setIsPaid(false);
        $order->addStatusHistoryComment(
            Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'),
            false
        );
        $invoice->save();
        if ($this->_invoiceOrderEmail) {
            $code = $order->getPayment()->getMethodInstance()->getCode();
            if ($code == 'hcdiv' or $code == 'hcdivsec') {
                $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
                $invoiceMailComment = ($info === false) ? '' : '<h3>'
                    . $this->_getHelper()->__('payment information') . '</h3><p>' . $info . '</p>';
            }

            $invoice->sendEmail(true, $invoiceMailComment); // send invoice mail
        }


        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        $this->log('Set transaction to processed and generate invoice ');
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusPendig(false),
            $order->getPayment()->getMethodInstance()->getStatusPendig(true),
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
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function processingTransaction($order, $data, $message = '')
    {

        /** @var  $paymentHelper HeidelpayCD_Edition_Helper_Payment */
        $paymentHelper = Mage::helper('hcd/payment');


        $message = ($message === '') ? 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] : $message;
        $totallyPaid = false;

        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(true);

        if ($paymentHelper->format($order->getGrandTotal()) == $data['PRESENTATION_AMOUNT'] and
            $order->getOrderCurrencyCode() == $data['PRESENTATION_CURRENCY']
        ) {
            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusSuccess(false),
                $order->getPayment()->getMethodInstance()->getStatusSuccess(true),
                $message
            );
            $totallyPaid = true;
        } else {
            // in case rc is ack and amount is to low or currency miss match

            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(false),
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(true),
                $message
            );
        }

        // Set invoice to paid when the total amount matches
        if ($order->hasInvoices() and $totallyPaid) {

            /** @var  $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($order->getInvoiceCollection() as $invoice) {
                $this->log('Set invoice ' . (string)$invoice->getIncrementId() . ' to paid.');
                $invoice
                    ->capture()
                    ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                    ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                    ->setIsPaid(true)
                    // @codingStandardsIgnoreLine use of save in a loop
                    ->save();

                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                // @codingStandardsIgnoreLine use of save in a loop
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

        $order->setIsInProcess(true);

        return $order;
    }
}
