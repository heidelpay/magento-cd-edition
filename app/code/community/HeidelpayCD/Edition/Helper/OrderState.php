<?php

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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Helper_OrderState extends HeidelpayCD_Edition_Helper_AbstractHelper
{
    /**
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $order Mage_Sales_Model_Order
     * @param string $message order history message
     */
    public function mapStatus($data, $order, $message = '')
    {
        $this->log('mapStatus' . json_encode($data));
        $paymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);

        $message = ($message === '') ? $data['PROCESSING_RETURN'] : $message;


        // Set language for mail template etc
        if (strtoupper($data['CRITERION_LANGUAGE']) == 'DE') {
            $locale = 'de_DE';
            Mage::app()->getLocale()->setLocaleCode($locale);
            Mage::getSingleton('core/translate')->setLocale($locale)->init('frontend', true);
        }


        // handle charge back notifications for cc, dc and dd
        if ($paymentCode[1] == 'CB') {
            $this->log('charge back for order ' . $order->getIncrementId());

            $order->getPayment()->getMethodInstance()->chargeBackTransaction($order);

            Mage::dispatchEvent('heidelpay_after_map_status_chargeBack', array('order' => $order));
            $this->log('Is this order protected ? ' . (string)$order->isStateProtected());
            $order->save();
            return;
        }

        // Do nothing if status is already successful
        if ($order->getStatus() == $order->getPayment()->getMethodInstance()->getStatusSuccess()) {
            return;
        }

        // If the order is canceled, closed or complete do not change order status
        if ($order->getStatus() == Mage_Sales_Model_Order::STATE_CANCELED or
            $order->getStatus() == Mage_Sales_Model_Order::STATE_CLOSED or
            $order->getStatus() == Mage_Sales_Model_Order::STATE_COMPLETE
        ) {
            // you can use this event for example to get a notification when a canceled order has been paid
            $this->log('Order '.$order->getRealOrderId().' is canceled, closed or complete.');
            Mage::dispatchEvent('heidelpay_map_status_cancel', array('order' => $order, 'data' => $data));
            return;
        }

        // Set status for transaction that are not ok
        if ($data['PROCESSING_RESULT'] == 'NOK') {
            $order->getPayment()->getMethodInstance()->canceledTransaction($order, $message);
            Mage::dispatchEvent('heidelpay_after_map_status_canceled', array('order' => $order));
            $order->save();

            return;
        }

        // Set status for transaction with payment transaction
        $paidTransactionTypes = array('CP', 'DB', 'RC', 'FI');

        if (in_array($paymentCode[1], $paidTransactionTypes)
            and    ($data['PROCESSING_RESULT'] == 'ACK' and $data['PROCESSING_STATUS_CODE'] != 80)
        ) {
            // only Billsafe finalize will have impacted on the order status
            if ($paymentCode[1] === 'FI' and $data['ACCOUNT_BRAND'] !== 'BILLSAFE') {
                return;
            }

            $order->getPayment()->getMethodInstance()->processingTransaction($order, $data, $message);

            Mage::dispatchEvent('heidelpay_after_map_status_processed', array('order' => $order));
            $order->save();
            return;
        }

        //
        if ($order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusSuccess() and
            $order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusError()
        ) {
            $order->getPayment()->getMethodInstance()->pendingTransaction($order, $data, $message);
        }


        Mage::dispatchEvent('heidelpay_after_map_status', array('order' => $order));
        $order->save();
    }
}
