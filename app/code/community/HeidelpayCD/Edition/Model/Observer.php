<?php
/**
 * heidelpay observer model
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * @copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
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
class HeidelpayCD_Edition_Model_Observer
{
    public $invoiceOrderEmail = true;

    /**
     * Unset session variable hcdWallet if the customers return to backet
     *
     * @param $observer
     */
    public function removeWalletDataFromCheckout()
    {
        // unset wallet data from session
        if ($session = Mage::getSingleton('checkout/session')) {
            $session->unsHcdWallet();
        }
    }

    /**
     * Unset session variable hcdWallet if the customers return to backet
     *
     * @param $observer
     */
    public function handleWalletDataDuringCheckout($observer)
    {
        $controller = $observer->getControllerAction();

        $controllerName = $controller->getRequest()->getControllerName();

        $actionName = $controller->getRequest()->getActionName();

        if (($controllerName == "cart" and $actionName == "index")
            or ($controllerName == "onepage" and $actionName == "index")
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
     * @param $observer
     */
    public function reportShippingToHeidelpay($observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        if (empty($order)) {
            return $this;
        }

        $payment = $order->getPayment()->getMethodInstance();

        $paymentCode = $payment->getCode();


        $paymentMethod = array('hcdivsec', 'hcdbs');


        // return $this when reporting shipment is not needed
        if (!in_array($paymentCode, $paymentMethod)) {
            return $this;
        }

        /** @var $transactionModel HeidelpayCD_Edition_Model_Transaction */
        $transactionModel = Mage::getModel('hcd/transaction');
        /** @var $sessionModel Mage_Core_Model_Session */
        $sessionModel = Mage::getSingleton('core/session');
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
        $config = $payment->getMainConfig($paymentCode, $order->getStoreId());
        $config['PAYMENT.TYPE'] = 'FI';

        // set frontend parameter for request
        $frontend = $payment->getFrontend($order->getRealOrderId(), $authorisation['CRITERION_STOREID']);
        $frontend['FRONTEND.MODE'] = 'DEFAULT';
        $frontend['FRONTEND.ENABLED'] = 'false';

        // set user parameter for request
        $user = $payment->getUser($order, true);

        $basketData = $payment->getBasketData($order);
        $basketData['IDENTIFICATION.REFERENCEID'] = $authorisation['IDENTIFICATION_UNIQUEID'];#

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

        $this->log("doRequest url : " . $config['URL']);
        $this->log("doRequest params : " . json_encode($params));
        // send shipment report to heidelpay api
        $src = $paymentHelper->doRequest($config['URL'], $params);

        $this->log("doRequest response : " . json_encode($src));

        // generate error message in case of api error
        if ($src['PROCESSING_RESULT'] == "NOK") {
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
            $order->getPayment()->getMethodInstance()->getStatusPendig(false),
            $order->getPayment()->getMethodInstance()->getStatusPendig(true),
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
    protected function log($message, $level = "DEBUG", $file = false)
    {
        $callers = debug_backtrace();
        return Mage::helper('hcd/payment')
            ->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }
}
