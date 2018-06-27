<?php
/**
 * heidelpay observer model
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * @copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Observer
{
    public $invoiceOrderEmail = true;

    /**
     * Unset session variable hcdWallet if the customers return to backet
     */
    public function removeWalletDataFromCheckout()
    {
        // unset wallet data from session
        if ($session = Mage::getSingleton('checkout/session')) {
            $session->unsHcdWallet();
        }
    }

    /**
     * Unset session variable hcdWallet if the customers returns to basket.
     *
     * @param Varien_Event_Observer $observer
     */
    public function handleWalletDataDuringCheckout($observer)
    {
        if ($observer === null) {
            return;
        }

        $controller = $observer->getControllerAction();

        $controllerName = $controller->getRequest()->getControllerName();

        $actionName = $controller->getRequest()->getActionName();

        if (($controllerName === 'cart' && $actionName === 'index')
            or ($controllerName === 'onepage' && $actionName === 'index')
        ) {
            /**
             * remove wallet information from session (currently only masterpass)
             */
            if ($session = Mage::getSingleton('checkout/session')) {
                $session->unsHcdWallet();
            }
        }
    }

    /**
     * Observer on save shipment to report the shipment to heidelpay
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     * @throws \Mage_Core_Exception
     */
    public function reportReversalToHeidelpay($observer)
    {
        /** @var Mage_Core_Model_Session $sessionModel */
        $sessionModel = Mage::getSingleton('core/session');

        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        if ($observer === null) {
            $sessionModel->addNotice(
                $paymentHelper->__('Due to technical circumstances the Reversal Notice cannot be sent to heidelpay.')
            );

            return $this;
        }

        /** @var Varien_Event $event */
        $event = $observer->getEvent();
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $event->getInvoice();
        $payment = $event->getPayment();

        /** @var Mage_Payment_Model_Method_Abstract $paymentMethodInstance */
        $paymentMethodInstance = $payment->getMethodInstance();
        if (!$paymentMethodInstance instanceof HeidelpayCD_Edition_Model_Payment_Abstract) {
            return $this;
        }

        // if the payment method is not supporting reversals, stop here.
        if (!$paymentMethodInstance->canReversal()) {
            return $this;
        }

        /**  @var $transaction HeidelpayCD_Edition_Model_Transaction */
        $transaction = Mage::getModel('hcd/transaction');

        // load authorisation transaction form database
        $authenticationTransaction = $transaction->getOneTransactionByMethode(
            $invoice->getOrder()->getRealOrderId(),
            'PA'
        );

        if ($authenticationTransaction === false) {
            $sessionModel->addNotice(
                $paymentHelper->__(
                    'Reversal transaction is not possible here, since there was no Authorize transaction.'
                )
            );
            return $this;
        }

        // if reversal transaction fails
        if (!$paymentMethodInstance->reversal($invoice, $payment)) {
            $sessionModel->addError(
                $paymentHelper->__(
                    'Reversal transaction to heidelpay failed.'
                )
            );
            return $this;
        }

        $sessionModel->addSuccess($paymentHelper->__('Reversal transaction to heidelpay succeeded.'));
        return $this;
    }

    /**
     * Observer on save shipment to report the shipment to heidelpay
     *
     * @param Varien_Event_Observer $observer
     */
    public function reportShippingToHeidelpay($observer)
    {
        /** @var Mage_Core_Model_Session $sessionModel */
        $sessionModel = Mage::getSingleton('core/session');

        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        if ($observer === null) {
            $sessionModel->addNotice(
                $paymentHelper->__('Due to technical circumstances the Shipping Notice cannot be sent to heidelpay.')
            );

            return $this;
        }

        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        if (empty($order)) {
            return $this;
        }

        /** @var HeidelpayCD_Edition_Model_Payment_Abstract $payment */
        $payment = $order->getPayment()->getMethodInstance();

        // if no finalize needs to be sent to heidelpay, or the payment
        // method instance is not a heidelpay one, stop here.
        if (!$payment instanceof HeidelpayCD_Edition_Model_Payment_Abstract
            || !$payment->reportsShippingToHeidelpay()
        ) {
            return $this;
        }

        /** @var $transactionModel HeidelpayCD_Edition_Model_Transaction */
        $transactionModel = Mage::getModel('hcd/transaction');

        /** @var  $heidelpayHelper HeidelpayCD_Edition_Helper_Data */
        $heidelpayHelper = Mage::helper('hcd');

        /** @var  $paymentHelper HeidelpayCD_Edition_Helper_Payment */
        $paymentHelper = Mage::helper('hcd/payment');

        // load authorisation form database
        $authorisation = $transactionModel->getOneTransactionByMethode($order->getRealOrderId(), 'PA');
        // no authorisation found
        if ($authorisation === false) {
            $sessionModel->addError(
                $heidelpayHelper->__(
                    'Delivery notes to Heidelpay fail, because of no initial authorisation'
                )
            );
            return $this;
        }

        // set config parameter for request
        $config = $payment->getMainConfig($payment->getCode(), $order->getStoreId());
        $config['PAYMENT.TYPE'] = 'FI';

        // set frontend parameter for request
        $frontend = $payment->getFrontend($order->getRealOrderId(), $authorisation['CRITERION_STOREID']);
        $frontend['FRONTEND.MODE'] = 'DEFAULT';
        $frontend['FRONTEND.ENABLED'] = 'false';

        // set user parameter for request
        $user = $payment->getUser($order, true);

        $basketData = $payment->getBasketData($order);
        $basketData['IDENTIFICATION.REFERENCEID'] = $authorisation['IDENTIFICATION_UNIQUEID'];

        $criterion = array();

        Mage::dispatchEvent(
            'heidelpay_reportShippingToHeidelpay_bevor_preparePostData',
            array(
                'payment' => $payment,
                'config' => $config,
                'frontend' => $frontend,
                'user' => $user,
                'basketData' => $basketData,
                'criterion' => $criterion
            )
        );

        // prepare shipment report
        $params = $paymentHelper->preparePostData($config, $frontend, $user, $basketData, $criterion);

        $this->log('Finalize url : ' . $config['URL']);
        $this->log('Finalize params : ' . json_encode($params));
        // send shipment report to heidelpay api
        $src = $paymentHelper->doRequest($config['URL'], $params);

        $this->log('Finalize response : ' . json_encode($src));

        // generate error message in case of api error
        if ($src['PROCESSING_RESULT'] === 'NOK') {
            $sessionModel
                ->addError(
                    $heidelpayHelper
                        ->__(
                            'Delivery notes to Heidelpay fail, because of : 
                                    '
                        )
                    . $src['PROCESSING_RETURN']
                );
            $shipment->_dataSaveAllowed = false;
            Mage::app()->getResponse()
                // @codingStandardsIgnoreLine use of S_SERVER is discouraged
                ->setRedirect($_SERVER['HTTP_REFERER'])
                ->sendResponse();
            return;
        }

        $message = $heidelpayHelper->__('report shipment to heidelpay successful. Waiting for receipt of money');
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusPending(false),
            $order->getPayment()->getMethodInstance()->getStatusPending(true),
            $message
        )->save();


        // successful send shipment report to heidelpay
        $sessionModel
            ->addSuccess(
                $heidelpayHelper
                    ->__('Successfully report delivery to Heidelpay.')
            );
    }

    /**
     * log function
     *
     * @param $message string log message
     * @param $level string log level
     * @param $file string log file
     *
     * @return mixed
     */
    protected function log($message, $level = 'DEBUG', $file = false)
    {
        $callers = debug_backtrace();
        return Mage::helper('hcd/payment')
            ->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }

}
