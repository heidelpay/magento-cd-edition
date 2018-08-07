<?php
/**
 * Description
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.de>
 *
 * @package  heidelpay/${Package}
 */

class HeidelpayCD_Edition_Helper_InvoiceHelper
{
    /**
     * logger
     *
     * @param $message string message that should be logged
     * @param string $level message level (like debug,info or warning)
     * @param bool   $file  name of the logfile
     *
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    public function log($message, $level = 'DEBUG', $file = false)
    {
        $callers = debug_backtrace();
        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');
        return $paymentHelper->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }

    /**
     * Return true if actual equals expected currency.
     *
     * @param Mage_Sales_Model_Order $order
     * @param HeidelpayCD_Edition_Model_Transaction $data
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    public function validateCurrency(Mage_Sales_Model_Order $order, $data)
    {
        if ($order->getOrderCurrencyCode() !== $data['PRESENTATION_CURRENCY']) {
            $this->log(
                sprintf(
                    'Currency mismatch for order #%s. expected: [%s], actual: [%s]',
                    $order->getRealOrderId(),
                    $order->getOrderCurrencyCode(),
                    $data['PRESENTATION_CURRENCY']
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Handle incoming payments to an invoice.
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function handleInvoicePayment($order, $data, $message = '')
    {
        $message = ($message === '') ? 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] : $message;
        $totallyPaid = false;

        if (!$this->validateCurrency($order, $data)) {
            return $order;
        }

        /** @var Mage_Sales_Model_Order_Payment $orderPayment */
        $orderPayment = $order->getPayment();

        /** @var HeidelpayCD_Edition_Model_Payment_Abstract $paymentMethodInstance */
        $paymentMethodInstance = $orderPayment->getMethodInstance();

        /** @noinspection PhpUndefinedMethodInspection */
        $orderPayment
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(true);

        $paidAmount = (float) $data['PRESENTATION_AMOUNT'];
        $dueLeft = $order->getTotalDue() - $paidAmount;
        $totalPaid = $order->getTotalPaid() + $paidAmount;

        if ($dueLeft === 0.00) {
            $order->setState(
                $paymentMethodInstance->getStatusSuccess(),
                $paymentMethodInstance->getStatusSuccess(true),
                $message
            );

            $totallyPaid = true;
        }

        if ($dueLeft < 0.00) {
            $comment = sprintf(
                'Customer paid too much: %s%.2f',
                Mage::app()->getLocale()->currency($order->getOrderCurrencyCode())->getSymbol(),
                $dueLeft * -1
            );

            $order->setState(
                $paymentMethodInstance->getStatusPartlyPaid(),
                $paymentMethodInstance->getStatusPartlyPaid(),
                $comment
            );

            $totallyPaid = true;
        }

        if ($dueLeft > 0.00) {
            $order->setState(
                $paymentMethodInstance->getStatusPartlyPaid(),
                $paymentMethodInstance->getStatusPartlyPaid(true),
                $message
            );
        }

        // Set invoice to paid when the total amount matches
        if ($totallyPaid && $order->hasInvoices()) {
            /** @var Mage_Sales_Model_Resource_Order_Invoice_Collection $invoices */
            $invoices = $order->getInvoiceCollection();

            /** @var  $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($invoices as $invoice) {
                $this->log('Set invoice ' . $invoice->getIncrementId() . ' to paid.');
                /** @noinspection PhpUndefinedMethodInspection */
                $invoice
                    ->capture()
                    ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                    ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                    ->setIsPaid(true)
                    // @codingStandardsIgnoreLine use of save in a loop
                    ->save();

                /** @noinspection PhpUndefinedMethodInspection */
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transactionSave->save();
            }
        }

        // Set total paid and invoice to the connector amount
        $order
            ->setTotalInvoiced($totalPaid)
            ->setTotalPaid($totalPaid)
            ->setTotalDue($dueLeft);

        $orderPayment->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            null,
            true
        );

        // close the parent transaction if no due is left.
        if ($totallyPaid) {
            /** @noinspection PhpUndefinedMethodInspection */
            $orderPayment->setShouldCloseParentTransaction(true);
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setIsInProcess(true);

        return $order;
    }
}
