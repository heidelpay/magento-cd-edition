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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_IndexController extends Mage_Core_Controller_Front_Action
{
    protected $_sendNewOrderEmail = true;
    protected $_invoiceOrderEmail = true;
    protected $_order = null;
    protected $_paymentInst = null;
    protected $_debug = true;

    protected $_liveUrl = 'https://heidelpay.hpcgw.net/ngw/post';
    protected $_sandboxUrl = 'https://test-heidelpay.hpcgw.net/ngw/post';


    protected $_liveBasketUrl = 'https://heidelpay.hpcgw.net/ngw/basket/';
    protected $_sandboxBasketUrl = 'https://test-heidelpay.hpcgw.net/ngw/basket/';


    public $importantPPFields = array(
        'PRESENTATION_AMOUNT',
        'PRESENTATION_CURRENCY',
        'CONNECTOR_ACCOUNT_COUNTRY',
        'CONNECTOR_ACCOUNT_HOLDER',
        'CONNECTOR_ACCOUNT_NUMBER',
        'CONNECTOR_ACCOUNT_BANK',
        'CONNECTOR_ACCOUNT_BIC',
        'CONNECTOR_ACCOUNT_IBAN',
        'IDENTIFICATION_SHORTID',
    );

    /** @var $_basketApiHelper HeidelpayCD_Edition_Helper_BasketApi  */
    protected $_basketApiHelper;

    protected function _getHelper()
    {
        return Mage::helper('hcd');
    }

    /**
     * HeidelpayCD_Edition_IndexController constructor.
     *
     * @param Zend_Controller_Request_Abstract     $request
     * @param Zend_Controller_Response_Abstract    $response
     * @param HeidelpayCD_Edition_Helper_BasketApi $basketApiHelper
     * @param array                                $invokeArgs
     */
    // @codingStandardsIgnoreLine bug in multi line standard
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        parent::__construct($request, $response, $invokeArgs);
        $this->_basketApiHelper =    Mage::helper('hcd/basketApi');
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
     * @return Mage_Heidelpay_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('core/session');
    }

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

    /**
     * successful return from Heidelpay payment
     */
    public function successAction()
    {
        $session = $this->getCheckout();
        $order = Mage::getModel('sales/order');
        $this->log('SuccessAction Session: '. json_encode($session->getLastRealOrderId()));
        $order->loadByIncrementId($session->getLastRealOrderId());
        if ($order->getPayment() === false) {
            $this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
            return $this;
        }

        $noMail = (Mage::app()->getRequest()->getParam('no_mail') == 1) ? true : false;


        $this->getCheckout()->getQuote()->setIsActive(false)->save();
        $this->getCheckout()->clear();

        $message = "";

        $data = Mage::getModel('hcd/transaction')
            ->loadLastTransactionDataByTransactionnr($session->getLastRealOrderId());

        $this->log('SuccessAction Data: '. json_encode($data));

        /*
         * validate Hash to prevent manipulation
         */
        if (Mage::getModel('hcd/resource_encryption')
                ->validateHash($data['IDENTIFICATION_TRANSACTIONID'], $data['CRITERION_SECRET']) === false
        ) {
            $this->log(
                "Customer tries to redirect directly to success page. IP "
                . Mage::app()->getRequest()->getServer('REMOTE_ADDR')
                . " . This could be some kind of manipulation.", 'WARN'
            );
            $this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
        }

        $session->unsHcdPaymentInfo();

        if ($order->getPayment()->getMethodInstance()->getCode() != 'hcdiv' and
            $order->getPayment()->getMethodInstance()->getCode() != 'hcdivsec') {
            $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
            if ($info !== false) {
                $session->setHcdPaymentInfo($info);
                $order->setCustomerNote($info);
            }
        }

        // last_quote_id workaround for trusted shop buyerprotection
        $quoteID = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId();
        $this->getCheckout()->setLastSuccessQuoteId($quoteID);
        $this->log('LastQuteID :' . $quoteID);

        if ($noMail === false) {
            /** @var  $orderStateHelper HeidelpayCD_Edition_Helper_OrderState */
            $orderStateHelper = Mage::helper('hcd/orderState');
            $orderStateHelper->mapStatus(
                $data,
                $order
            );
        }

        if ($order->getId() and $noMail === false) {
            $order->sendNewOrderEmail();
        }

        $order->save();
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }

    public function errorAction()
    {
        $session = $this->getCheckout();
        $errorCode = null;
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $this->log(' LastRealOrderId ' . json_encode($session->getLastRealOrderId()));

        $request = Mage::app()->getRequest();
        $getError = $request->getParam('HPError');


        // in case of an error in the server to server request
        $usersession = $this->getSession();
        $data = Mage::getModel('hcd/transaction')
            ->loadLastTransactionDataByTransactionnr($session->getLastRealOrderId());
        $this->log(' data ' . json_encode($data));

        if ($usersession->getHcdError() !== null) {
            $message = Mage::helper('hcd/payment')
                ->handleError($usersession->getHcdError(), $errorCode, (string)$order->getRealOrderId());
            $intMessage = $usersession->getHcdError();
            $data['PROCESSING_RESULT'] = 'NOK';
            $usersession->unsHcdError();
        } else {
            if (isset($data['PROCESSING_RETURN_CODE'])) {
                $errorCode = $data['PROCESSING_RETURN_CODE'];
            }

            if (isset($getError)) {
                $errorCode = $getError;
                $data['PROCESSING_RESULT'] = 'NOK';
            }

            $message = Mage::helper('hcd/payment')
                ->handleError($data['PROCESSING_RETURN'], $errorCode, (string)$order->getRealOrderId());
            $intMessage = (!empty($data['PROCESSING_RETURN'])) ? $data['PROCESSING_RETURN'] : $message;
        }

        $quoteId = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId();
        // last_quote_id workaround for trusted shop buyerprotection

        if ($quoteId) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }

        /** @var  $orderStateHelper HeidelpayCD_Edition_Helper_OrderState */
        $orderStateHelper = Mage::helper('hcd/orderState');
        $orderStateHelper->mapStatus(
            $data,
            $order,
            $intMessage
        );

        $storeId = Mage::app()->getStore()->getId();
        $redirectController = Mage::getStoreConfig("hcd/settings/returnurl", $storeId);

        switch ($redirectController) {
            case "basket":
                $session->addError($message);
                $this->_redirect('checkout/cart', array('_secure' => true));
                break;
            default:
                $usersession->addError($message);
                $this->_redirect('checkout/onepage', array('_secure' => true));
        }
    }

    /**
     * redirect return from Heidelpay payment (iframe)
     */
    public function indexAction()
    {
        $data = array();
        $order = $this->getOrder();

        $refId = false;
        $basketId = false;

        $session = $this->getCheckout();
        $order->loadByIncrementId($session->getLastRealOrderId());
        if ($order->getPayment() === false) {
            $this->getResponse()->setRedirect(Mage::helper('customer')->getLoginUrl());
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }

        $payment = $order->getPayment()->getMethodInstance();

        if ($session->getHcdWallet() !== false) {
            $wallet = $session->getHcdWallet();
            $refId = (!empty($wallet['referenceId'])) ? $wallet['referenceId'] : false;
            $this->log('Wallet reference id :' . $refId);
        }


        if ($payment->canBasketApi() and empty($refId)) {
            $shoppingCart = $this->_basketApiHelper->basketItems($order, $this->getStore());

            $url = (Mage::getStoreConfig('hcd/settings/transactionmode', $this->getStore()) == 0)
                ? $this->_liveBasketUrl : $this->_sandboxBasketUrl;

            $this->log("doRequest shoppingcart : " . json_encode($shoppingCart), 'DEBUG');

            $result = Mage::helper('hcd/payment')->doRequest($url, array('raw' => $shoppingCart));

            if (array_key_exists('result', $result) && $result['result'] == 'NOK') {
                $this->log(
                    'Send basket to payment  fail, because of : ' .
                    json_encode($result), 'ERROR'
                );
                Mage::getSingleton('core/session')->setHcdError($result['basketErrors']['message']);
                $this->_redirect(
                    'hcd/index/error',
                    array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
                );
                return;
            }

            $this->log(
                "doRequest shopping cart response : " .
                json_encode($result), 'DEBUG'
            );
            $basketId = (array_key_exists('basketId', $result)) ? $result['basketId'] : false;
        }


        // if order status is cancel redirect to cancel page
        if ($order->getStatus() == $payment->getStatusError()) {
            $this->_redirect(
                'hcd/index/error',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
            );
            return;
        }

        // if order status is success redirect to success page
        if ($order->getStatus() == $payment->getStatusSuccess() or $order->getStatus() == $payment->getStatusPendig()) {
            $this->_redirect(
                'hcd/index/success',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true, 'no_mail' => true)
            );
            return;
        }


        $data = $payment->getHeidelpayUrl(false, $basketId, $refId);

        if ($data['POST_VALIDATION'] == 'ACK' and $data['PROCESSING_RESULT'] == 'ACK') {
            if ($data['PAYMENT_CODE'] == "OT.PA") {
                $quoteID = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId();
                // last_quote_id workaround for trusted shop buyerprotection
                $order->getPayment()->setTransactionId($quoteID);
                $order->getPayment()->setIsTransactionClosed(true);
            }

            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusPendig(false),
                $order->getPayment()->getMethodInstance()->getStatusPendig(true),
                Mage::helper('hcd')->__('Get payment url from Heidelpay -> ') . $data['FRONTEND_REDIRECT_URL']
            );
            $order->getPayment()->addTransaction(
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                null,
                true
            );
            $order->save();

            $session->getQuote()->setIsActive(true)->save();
            $session->clear();

            if ($payment->activeRedirect() === true) {
                $this->_redirectUrl($data['FRONTEND_REDIRECT_URL']);
                return;
            }

            $this->loadLayout();
            $this->log('RedirectUrl ' . $data['FRONTEND_PAYMENT_FRAME_URL']);
            $this->log('CCHolder ' . $payment->getCustomerName());
            $this->getLayout()->getBlock('hcd_index')->setHcdUrl($data['FRONTEND_PAYMENT_FRAME_URL']);
            $this->getLayout()->getBlock('hcd_index')->setHcdCode($payment->getCode());
        } else {
            Mage::getModel('hcd/transaction')->saveTransactionData($data);
            Mage::getSingleton('core/session')->setHcdError($data['PROCESSING_RETURN']);
            $this->_redirect(
                'hcd/index/error',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
            );
        }


        $this->renderLayout();
        return $this;
    }

    /**
     * masterpass wallet controller
     */
    public function walletAction()
    {
        $data = array();
        $request = Mage::app()->getRequest();
        $paymentCode = $request->getParam('_wallet');
        $storeId = $this->getStore();
        $code = false;
        $mageBasketId = (string)$this->getCheckout()->getQuoteId();

        if ($paymentCode == 'hcdmpa') {
            $code = 'hcdmpa';
        }

        $quote = $this->getOnepage()->getQuote();
        if (!$quote->hasItems() || $quote->getHasError() || $code === false) {
            $this->_redirect('checkout/cart');
            return;
        }


        $shoppingCart = $this->_basketApiHelper->basketItems($quote, $storeId, false);

        $url = (Mage::getStoreConfig(
            'hcd/settings/transactionmode',
            $storeId
        ) == 0) ? $this->_liveBasketUrl : $this->_sandboxBasketUrl;

        $this->log("doRequest shoppingcart : " . json_encode($shoppingCart), 'DEBUG');
        $this->log("doRequest shoppingcart : " . json_encode($shoppingCart), 'DEBUG');

        $result = Mage::helper('hcd/payment')->doRequest($url, array('raw' => $shoppingCart));

        if (array_key_exists('result', $result) && $result['result'] == 'NOK') {
            $this->log(
                'Send basket to payment  fail, because of : ' .
                json_encode($result), 'ERROR'
            );
            $message = $this->_getHelper()
                ->__('An unexpected error occurred. Please contact us to get further information.');
            Mage::getSingleton('core/session')->addError($message);
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }

        $this->log("doRequest shoppingcart response : " . json_encode($result), 'DEBUG');

        $config = array(
            'PAYMENT.METHOD' => preg_replace('/^hcd/', '', $code),
            'SECURITY.SENDER' => Mage::getStoreConfig('hcd/settings/security_sender', $storeId),
            'TRANSACTION.MODE' => (Mage::getStoreConfig(
                'hcd/settings/transactionmode',
                $storeId
            ) == 0) ? 'LIVE' : 'CONNECTOR_TEST',
            'URL' => (Mage::getStoreConfig(
                'hcd/settings/transactionmode',
                $storeId
            ) == 0) ? $this->_liveUrl : $this->_sandboxUrl,
            'USER.LOGIN' => trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
            'USER.PWD' => trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),
            'TRANSACTION.CHANNEL' => trim(Mage::getStoreConfig('payment/' . $code . '/channel', $storeId)),
            'PAYMENT.TYPE' => 'IN'
        );
        $frontend = array(
            'FRONTEND.LANGUAGE' => Mage::helper('hcd/payment')->getLang(),
            'FRONTEND.RESPONSE_URL' => Mage::getUrl(
                'hcd/index/response',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
            ),
            'CRITERION.SECRET' => Mage::getModel('hcd/resource_encryption')->getHash($mageBasketId),
            'CRITERION.LANGUAGE' => strtolower(Mage::helper('hcd/payment')->getLang()),
            'CRITERION.STOREID' => $storeId,
            'SHOP.TYPE' => 'Magento ' . Mage::getVersion(),
            'SHOPMODULE.VERSION' => 'HeidelpayCD Edition - '
                . (string)Mage::getConfig()->getNode()->modules->HeidelpayCD_Edition->version,
            'WALLET.DIRECT_PAYMENT' => 'false'
        );

        $visitorData = Mage::getSingleton('core/session')->getVisitorData();

        $user = array(
            'IDENTIFICATION.SHOPPERID' => $visitorData['visitor_id'],
            'NAME.GIVEN' => ' - ',
            'NAME.FAMILY' => ' - ',
            'ADDRESS.STREET' => ' - ',
            'ADDRESS.ZIP' => ' - ',
            'ADDRESS.CITY' => ' - ',
            'ADDRESS.COUNTRY' => 'DE',
            'CONTACT.EMAIL' => 'dummy@heidelpay.de',
            'CONTACT.IP' => (filter_var(
                trim(Mage::app()->getRequest()->getClientIp()),
                FILTER_VALIDATE_IP
            )) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1'
        );

        $basketData = array(
            'PRESENTATION.AMOUNT' => Mage::helper('hcd/payment')->format($quote->getGrandTotal()),
            'PRESENTATION.CURRENCY' => $quote->getGlobalCurrencyCode(),
            'IDENTIFICATION.TRANSACTIONID' => $mageBasketId,
            'BASKET.ID' => (array_key_exists('basketId', $result)) ? $result['basketId'] : ''
        );

        $params = Mage::helper('hcd/payment')->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion = array()
        );


        $this->log("doRequest url : " . $config['URL'], 'DEBUG');
        $this->log("doRequest params : " . json_encode($params), 'DEBUG');
        $data = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
        $this->log("doRequest response : " . json_encode($data), 'DEBUG');


        if ($data['POST_VALIDATION'] == 'ACK' and $data['PROCESSING_RESULT'] == 'ACK') {
            /** Redirect on Success */
            $this->_redirectUrl(trim($data['FRONTEND_REDIRECT_URL']));
            return;
        } else {
            /** Error Case */
            $this->log('Wallet Redirect for ' . $code . ' fail, because of : ' . $data['PROCESSING_RETURN'], 'ERROR');
            $message = $this->_getHelper()
                ->__('An unexpected error occurred. Please contact us to get further information.');
            Mage::getSingleton('core/session')->addError($message);
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }
    }

    /**
     * Controller for push notification
     */
    public function pushAction()
    {
        $rawPost = false;
        $lastdata = null;
        $request = Mage::app()->getRequest();
        $rawPost = $request->getRawBody();

        if ($rawPost === false) {
            $this->_redirect('', array('_secure' => true));
        }

        /** Hack to remove a structure problem in criterion node */
        $rawPost = preg_replace('/<Criterion(\s+)name="(.+?)">(.+?)<\/Criterion>/', '<$2>$3</$2>', $rawPost);

        $xml = simplexml_load_string($rawPost);

        $this->log('XML Object from Push : ' . $rawPost);

        // @codingStandardsIgnoreStart simplexml notation
        list($type, $methode) = Mage::helper('hcd/payment')
            ->splitPaymentCode((string)$xml->Transaction->Payment['code']);

        if ($methode == 'RG') {
            return;
        }

        $hash = (string)$xml->Transaction->Analysis->SECRET;
        $orderID = (string)$xml->Transaction->Identification->TransactionID;
        // @codingStandardsIgnoreEnd

        if (Mage::getModel('hcd/resource_encryption')->validateHash($orderID, $hash) === false) {
            $this->log(
                "Get response form server " . Mage::app()->getRequest()->getServer('REMOTE_ADDR')
                . " with an invalid hash. This could be some kind of manipulation.",
                'WARN'
            );
            $this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
            return;
        }

        // @codingStandardsIgnoreStart simplexml notation
        $xmlData = array(
            'PAYMENT_CODE' => (string)$xml->Transaction->Payment['code'],
            'IDENTIFICATION_TRANSACTIONID' => (string)$orderID,
            'IDENTIFICATION_UNIQUEID' => (string)$xml->Transaction->Identification->UniqueID,
            'PROCESSING_RESULT' => (string)$xml->Transaction->Processing->Result,
            'IDENTIFICATION_SHORTID' => (string)$xml->Transaction->Identification->ShortID,
            'PROCESSING_STATUS_CODE' => (string)$xml->Transaction->Processing->Status['code'],
            'PROCESSING_RETURN' => (string)$xml->Transaction->Processing->Return,
            'PROCESSING_RETURN_CODE' => (string)$xml->Transaction->Processing->Return['code'],
            'PRESENTATION_AMOUNT' => (string)$xml->Transaction->Payment->Presentation->Amount,
            'PRESENTATION_CURRENCY' => (string)$xml->Transaction->Payment->Presentation->Currency,
            'IDENTIFICATION_REFERENCEID' => (string)$xml->Transaction->Identification->ReferenceID,
            'CRITERION_STOREID' => (int)$xml->Transaction->Analysis->STOREID,
            'ACCOUNT_BRAND' => false,
            'CRITERION_LANGUAGE' => strtoupper((string)$xml->Transaction->Analysis->LANGUAGE)
        );
        // @codingStandardsIgnoreEnd

        $order = $this->getOrder();
        $order->loadByIncrementId($orderID);
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();

        switch ($paymentCode) {
            case 'hcddd':
                // @codingStandardsIgnoreStart simplexml notation
                $xmlData['CLEARING_AMOUNT'] = (string)$xml->Transaction->Payment->Clearing->Amount;
                $xmlData['CLEARING_CURRENCY'] = (string)$xml->Transaction->Payment->Clearing->Currency;
                $xmlData['ACCOUNT_IBAN'] = (string)$xml->Transaction->Account->Iban;
                $xmlData['ACCOUNT_BIC'] = (string)$xml->Transaction->Account->Bic;
                $xmlData['ACCOUNT_IDENTIFICATION'] = (string)$xml->Transaction->Account->Identification;
                $xmlData['IDENTIFICATION_CREDITOR_ID'] = (string)$xml->Transaction->Identification->CreditorID;
                // @codingStandardsIgnoreEnd
                break;
            case 'hcdbs':
                if ($methode == 'FI') {
                    // @codingStandardsIgnoreStart simplexml notation
                    $xmlData['CRITERION_BILLSAFE_LEGALNOTE'] = (string)$xml->Transaction->Analysis->BILLSAFE_LEGALNOTE;
                    $xmlData['CRITERION_BILLSAFE_AMOUNT'] = (string)$xml->Transaction->Analysis->BILLSAFE_AMOUNT;
                    $xmlData['CRITERION_BILLSAFE_CURRENCY'] = (string)$xml->Transaction->Analysis->BILLSAFE_CURRENCY;
                    $xmlData['CRITERION_BILLSAFE_RECIPIENT'] = (string)$xml->Transaction->Analysis->BILLSAFE_RECIPIENT;
                    $xmlData['CRITERION_BILLSAFE_IBAN'] = (string)$xml->Transaction->Analysis->BILLSAFE_IBAN;
                    $xmlData['CRITERION_BILLSAFE_BIC'] = (string)$xml->Transaction->Analysis->BILLSAFE_BIC;
                    $xmlData['CRITERION_BILLSAFE_REFERENCE'] = (string)$xml->Transaction->Analysis->BILLSAFE_REFERENCE;
                    $xmlData['CRITERION_BILLSAFE_PERIOD'] = (string)$xml->Transaction->Analysis->BILLSAFE_PERIOD;
                    $xmlData['ACCOUNT_BRAND'] = 'BILLSAFE';
                    // @codingStandardsIgnoreEnd
                }
                break;
        }

        // @codingStandardsIgnoreLine simplexml notation
        if (!empty($xml->Transaction->Identification->UniqueID)) {
            $lastdata = Mage::getModel('hcd/transaction')
                ->loadLastTransactionDataByUniqeId($xmlData['IDENTIFICATION_UNIQUEID']);
        }

        if ($lastdata === false) {
            Mage::getModel('hcd/transaction')->saveTransactionData($xmlData, 'push');
        }


        $this->log('PaymentCode ' . $paymentCode);

        $this->log($type . " " . $methode);

        if ($methode == 'CB' or
            $methode == 'RC' or
            $methode == 'CP' or
            $methode == 'DB' or
            ($methode == 'FI' and $paymentCode == 'hcdbs')
        ) {
            /** @var  $orderStateHelper HeidelpayCD_Edition_Helper_OrderState */
            $orderStateHelper = Mage::helper('hcd/orderState');
            $orderStateHelper->mapStatus(
                $xmlData,
                $order
            );
        }
    }
}
