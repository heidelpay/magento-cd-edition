<?php
/*
/**
 * Order state Helper
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
class HeidelpayCD_Edition_Helper_OrderState extends Mage_Core_Helper_Abstract
{
    /**
     * @param $order Mage_Sales_Model_Order
     * @return Mage_Sales_Model_Order
     */
    public function chargeBack($order)
    {
        $paymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);

        $message = Mage::helper('hcd')->__('chargeback');

        if ($paymentCode[0] == 'DD') {
            // message block for direct debit charge back
            $message = Mage::helper('hcd')->__('debit failed');
        }

        if ($order->hasInvoices()) {
            $invIncrementIDs = array();
            foreach ($order->getInvoiceCollection() as $invoice) {
                $this->log('Invoice Number ' . (string)$invoice->getIncrementId());
                $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                $invoice->setIsPaid(false);
                $invoice->save();
            }

            $order->setIsInProcess(false);
            $order->setTotalInvoiced(0);
            $order->setTotalPaid(0);
        }

        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusPendig(false),
            true,
            $message
        );

        return $order;

    }
}