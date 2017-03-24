<?php

/**
 * Index controller
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
// @codingStandardsIgnoreLine
class HeidelpayCD_Edition_ResponseController extends Mage_Core_Controller_Front_Action
{
    protected $_sendNewOrderEmail = true;
    protected $_invoiceOrderEmail = true;
    protected $_order = null;
    protected $_paymentInst = null;
    protected $_debug = true;

    protected function _getHelper()
    {
        return Mage::helper('hcd');
    }

    protected function log($message, $level = "DEBUG", $file = false)
    {
        $callers = debug_backtrace();
        return Mage::helper('hcd/payment')->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }

    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            return false;
        }
    }

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::getModel('sales/order');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Get hp session namespace
     *
     * @return Mage_Core_Model_Abstract
     */
    public function getSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function getStore()
    {
        return Mage::app()->getStore()->getId();
    }

    public function indexAction()
    {
        $response = Mage::app()->getRequest();
        $response->setParamSources(array('_POST'));
        $data = array();

        $this->log('ResponseController');
        
        $securityHash = $response->getPost('CRITERION_SECRET');

        $transactionId = $response->getPOST('IDENTIFICATION_TRANSACTIONID');
        $data['IDENTIFICATION_TRANSACTIONID'] =
            (!empty($transactionId))
                ? $response->getPOST('IDENTIFICATION_TRANSACTIONID')
                : $response->getPOST('IDENTIFICATION_SHOPPERID');
        /*
         * validate Hash to prevent manipulation
         */
        if (Mage::getModel('hcd/resource_encryption')
                ->validateHash(
                    $data['IDENTIFICATION_TRANSACTIONID'],
                    $securityHash
                ) === false
        ) {
            // @codingStandardsIgnoreLine should be refactored - issue #5
            print Mage::getUrl(
                'hcd/index/error', array(
                            '_forced_secure' => true,
                            '_store_to_url' => true,
                            '_nosid' => true
                        )
            );
            $this->log(
                "Get response form server "
                . $response->getServer('REMOTE_ADDR')
                . " with an invalid hash. This could be some kind of manipulation.",
                'WARN'
            );
            return;
        }

        $data= $response->getParams();


        $paymentCode = Mage::helper('hcd/payment')->splitPaymentCode($data['PAYMENT_CODE']);

        $this->log("Post params: " . json_encode($data));

        if ($paymentCode[1] == 'RG') {
            if ($data['PROCESSING_RESULT'] == 'NOK') {
                $message = Mage::helper('hcd/payment')->handleError(
                    $data['PROCESSING_RETURN'],
                    $data['PROCESSING_RETURN_CODE']
                );
                $checkout = $this->getCheckout()->addError($message);
                $url = Mage::getUrl(
                    'hcd/index/error', array(
                    '_forced_secure' => true,
                    '_store_to_url' => true,
                    '_nosid' => true,
                    'HPError' => $data['PROCESSING_RETURN_CODE']
                    )
                );
            } else {
                // save cc and dc registration data
                $this->saveCustomerRegistration($data, $paymentCode);

                $url = Mage::getUrl('hcd/', array('_secure' => true));
            }
        } elseif ($paymentCode[1] == 'IN' and $response->getPost('WALLET_DIRECT_PAYMENT') == 'false') {
            // Back to checkout after wallet init
            if ($data['PROCESSING_RESULT'] == 'NOK') {
                $this->log(
                    'Wallet for basketId '
                    . $data['IDENTIFICATION_TRANSACTIONID']
                    . ' failed because of '
                    . $data['PROCESSING_RETURN'],
                    'NOTICE'
                );
                $url = Mage::getUrl('checkout/cart', array('_secure' => true));
            } else {
                $url = Mage::getUrl('hcd/checkout/', array('_secure' => true, '_wallet' => 'hcdmpa'));
            }

            Mage::getModel('hcd/transaction')->saveTransactionData($data);
        } else {
            /* load order */
            $order = $this->getOrder();
            $order->loadByIncrementId($data['IDENTIFICATION_TRANSACTIONID']);
            if ($order->getPayment() !== false) {
                $payment = $order->getPayment()->getMethodInstance();
            }

            $this->log('UniqeID: ' . $data['IDENTIFICATION_UNIQUEID']);


            if ($data['PROCESSING_RESULT'] == 'NOK') {
                if (isset($data['FRONTEND_REQUEST_CANCELLED'])) {
                    $url = $data['FRONTEND_FAILURE_URL'];
                } else {
                    $url = $data['FRONTEND_FAILURE_URL'];
                }
            } elseif (($paymentCode[1] == 'CP' or
                    $paymentCode[1] == 'DB' or
                    $paymentCode[1] == 'FI' or
                    $paymentCode[1] == 'RC')
                and ($data['PROCESSING_RESULT'] == 'ACK' and $data['PROCESSING_STATUS_CODE'] != 80)
            ) {
                $url = $data['FRONTEND_SUCCESS_URL'];
            } else {
                $url = $data['FRONTEND_SUCCESS_URL'];
            }

            Mage::getModel('hcd/transaction')->saveTransactionData($data);
        }

        $this->log('Url: ' . $url);

        // @codingStandardsIgnoreLine should be refactored - issue #5
        print $url;
    }

    /**
     * Save customer registration
     *
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $paymentCode array
     *
     * @return HeidelpayCD_Edition_Model_Customer
     */
    protected function saveCustomerRegistration($data, $paymentCode)
    {
        /** @var  $customerData HeidelpayCD_Edition_Model_Customer */
        $customerData = Mage::getModel('hcd/customer');
        $currentPayment = 'hcd' . strtolower($paymentCode[0]);
        $storeId = ($data['CRITERION_GUEST'] == 'true')
            ? 0 : trim($data['CRITERION_STOREID']);
        $registrationData = Mage::getModel('hcd/customer')
            ->getCollection()
            ->addFieldToFilter('Customerid', trim($data['IDENTIFICATION_SHOPPERID']))
            ->addFieldToFilter('Storeid', $storeId)
            ->addFieldToFilter('Paymentmethode', trim($currentPayment));
        $registrationData->load();
        $returnData = $registrationData->getData();
        if (!empty($returnData[0]['id'])) {
            $customerData->setId((int)$returnData[0]['id']);
        }

        $customerData->setPaymentmethode($currentPayment);
        $customerData->setUniqeid($data['IDENTIFICATION_UNIQUEID']);
        $customerData->setCustomerid($data['IDENTIFICATION_SHOPPERID']);
        $customerData->setStoreid($storeId);
        $customerData->setPaymentData(
            Mage::getModel('hcd/resource_encryption')
                ->encrypt(
                    json_encode(
                        array(
                            'ACCOUNT.REGISTRATION' => $data['IDENTIFICATION_UNIQUEID'],
                            'SHIPPPING_HASH' => $data['CRITERION_SHIPPPING_HASH'],
                            'ACCOUNT_BRAND' => $data['ACCOUNT_BRAND'],
                            'ACCOUNT_NUMBER' => $data['ACCOUNT_NUMBER'],
                            'ACCOUNT_HOLDER' => $data['ACCOUNT_HOLDER'],
                            'ACCOUNT_EXPIRY_MONTH' => $data['ACCOUNT_EXPIRY_MONTH'],
                            'ACCOUNT_EXPIRY_YEAR' => $data['ACCOUNT_EXPIRY_YEAR']
                        )
                    )
                )
        );

        return $customerData->save();
    }
}
