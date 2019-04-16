<?php
/**
 * Index controller
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
     * @param Zend_Controller_Request_Abstract  $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array                             $invokeArgs
     */
    // @codingStandardsIgnoreLine bug in multi line standard
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        parent::__construct($request, $response, $invokeArgs);
        $this->_basketApiHelper =    Mage::helper('hcd/basketApi');
    }

    protected function log($message, $level = 'DEBUG', $file = false)
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

        $data = Mage::getModel('hcd/transaction')
            ->loadLastTransactionDataByTransactionnr($session->getLastRealOrderId());

        ksort($data);
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

        /** @var HeidelpayCD_Edition_Model_Payment_Abstract $methodInstance */
        $methodInstance = $order->getPayment()->getMethodInstance();
        if ($methodInstance->isShowAdditionalPaymentInformation()) {
            $info = $methodInstance->showPaymentInfo($data);
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
            $intMessage = !empty($data['PROCESSING_RETURN']) ? $data['PROCESSING_RETURN'] : $message;
        }

        // remove payment method from selection if the customer has been rejected
        $payment = $order->getPayment()->getMethodInstance();
        if ($payment instanceof HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
            && $payment->remembersInsuranceDenial()) {
            if ((array_key_exists('PROCESSING_REASON', $data) &&
                    $data['PROCESSING_REASON'] === 'INSURANCE_ERROR') &&
                (array_key_exists('CRITERION_INSURANCE-RESERVATION', $data) &&
                    $data['CRITERION_INSURANCE-RESERVATION'] === 'DENIED')) {
                $paymentCode = $payment->getCode();
                $setCustomerRejected = 'set' . $paymentCode . 'CustomerRejected';
                $this->getCheckout()->$setCustomerRejected(true);
                $this->log(
                    'Remove payment method ' . $paymentCode .
                    ' from payment methods, since the customer has been revoked!'
                );
            }
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
        $orderStateHelper->mapStatus($data, $order, $intMessage);

        $storeId = Mage::app()->getStore()->getId();
        $redirectController = Mage::getStoreConfig('hcd/settings/returnurl', $storeId);

        switch ($redirectController) {
            case 'basket':
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
     *
     * @throws \Mage_Core_Exception
     */
    public function indexAction()
    {
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

        // set refId in case of masterpass quick checkout
        if ($session->getHcdWallet() !== false) {
            $wallet = $session->getHcdWallet();
            $refId = (!empty($wallet['referenceId'])) ? $wallet['referenceId'] : false;
            $this->log('Wallet reference id :' . $refId);
        }

        /** @var HeidelpayCD_Edition_Model_Payment_Abstract $payment */
        $payment = $order->getPayment()->getMethodInstance();
        if ($payment->canBasketApi() && empty($refId)) {
            // determine if shipping should be included to the basket for the heidelpay basket api
            $includeShipping = $order->getShippingAddress() ? true : false;

            $shoppingCart = $this->_basketApiHelper->basketItems($order, $this->getStore(), $includeShipping);

            $url = (Mage::getStoreConfig('hcd/settings/transactionmode', $this->getStore()) == 0)
                ? $this->_liveBasketUrl : $this->_sandboxBasketUrl;

            $this->log('Generated Basket : ' . json_encode($shoppingCart));

            $result = Mage::helper('hcd/payment')->doRequest($url, array('raw' => $shoppingCart));

            if (empty($result)) {
                Mage::getSingleton('core/session')->setHcdError('BasketApi request failed.');

                return $this->_redirect(
                    'hcd/index/error',
                    array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
                );
            }

            if (array_key_exists('result', $result) && $result['result'] === 'NOK') {
                $this->log(
                    'Send basket to payment  fail, because of : ' .
                    json_encode($result), 'ERROR'
                );
                Mage::getSingleton('core/session')->setHcdError($result['basketErrors']['message']);
                return $this->_redirect(
                    'hcd/index/error',
                    array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
                );
            }

            $this->log('Basket API Response :' . json_encode($result));
            $basketId = array_key_exists('basketId', $result) ? $result['basketId'] : false;
        }

        $orderStatus = $order->getStatus();

        // if order status is cancel redirect to cancel page
        if ($orderStatus === $payment->getStatusError()) {
            return $this->_redirect(
                'hcd/index/error',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)
            );
        }

        // if order status is success redirect to success page
        if ($orderStatus === $payment->getStatusSuccess() || $orderStatus === $payment->getStatusPending()) {
            return $this->_redirect(
                'hcd/index/success',
                array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true, 'no_mail' => true)
            );
        }

        $data = $payment->getHeidelpayUrl(false, $basketId, $refId);

        if ($data['POST_VALIDATION'] === 'ACK' && $data['PROCESSING_RESULT'] === 'ACK') {
            if ($data['PAYMENT_CODE'] === 'OT.PA') {
                $quoteID = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId();
                // last_quote_id workaround for trusted shop buyerprotection
                $order->getPayment()->setTransactionId($quoteID);
                $order->getPayment()->setIsTransactionClosed(true);
            }

            $order->setState(
                $payment->getStatusPending(),
                $payment->getStatusPending(true),
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

        $this->log('doRequest shoppingcart : ' . json_encode($shoppingCart));

        $result = Mage::helper('hcd/payment')->doRequest($url, array('raw' => $shoppingCart));

        if (array_key_exists('result', $result) && $result['result'] === 'NOK') {
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

        $this->log('doRequest shoppingcart response : ' . json_encode($result));

        $config = array(
            'PAYMENT.METHOD' => preg_replace('/^hcd/', '', $code),
            'SECURITY.SENDER' => Mage::getStoreConfig('hcd/settings/security_sender', $storeId),
            'TRANSACTION.MODE' => (Mage::getStoreConfig('hcd/settings/transactionmode', $storeId)) == 0
                ? 'LIVE'
                : 'CONNECTOR_TEST',
            'URL' => ((Mage::getStoreConfig('hcd/settings/transactionmode', $storeId) == 0))
                ? $this->_liveUrl
                : $this->_sandboxUrl,
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
            'SHOP.TYPE' => sprintf('Magento %s %s', Mage::getEdition(), Mage::getVersion()),
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
            'CONTACT.EMAIL' => 'dummy@heidelpay.com',
            'CONTACT.IP' => (filter_var(
                trim(Mage::app()->getRequest()->getClientIp()),
                FILTER_VALIDATE_IP
            )) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1'
        );

        $basketData = array(
            'PRESENTATION.AMOUNT' => Mage::helper('hcd/payment')->format($quote->getGrandTotal()),
            'PRESENTATION.CURRENCY' => $quote->getQuoteCurrencyCode(),
            'IDENTIFICATION.TRANSACTIONID' => $mageBasketId,
            'BASKET.ID' => array_key_exists('basketId', $result) ? $result['basketId'] : ''
        );

        $params = Mage::helper('hcd/payment')->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion = array()
        );

        $this->log('doRequest url : ' . $config['URL']);
        $this->log('doRequest params : ' . json_encode($params));
        $data = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
        $this->log('doRequest response : ' . json_encode($data));


        if ($data['POST_VALIDATION'] === 'ACK' && $data['PROCESSING_RESULT'] === 'ACK') {
            /** Redirect on Success */
            return $this->_redirectUrl(trim($data['FRONTEND_REDIRECT_URL']));
        }

        /** Error Case */
        $this->log('Wallet Redirect for ' . $code . ' fail, because of : ' . $data['PROCESSING_RETURN'], 'ERROR');
        $message = $this->_getHelper()
            ->__('An unexpected error occurred. Please contact us to get further information.');

        Mage::getSingleton('core/session')->addError($message);
        return $this->_redirect('checkout/cart', array('_secure' => true));
    }

    /**
     * Controller for push notification
     */
    public function pushAction()
    {
        $lastData = null;
        $request = Mage::app()->getRequest();
        $rawPost = $request->getRawBody();

        if ($rawPost === false) {
            $this->_redirect('', array('_secure' => true));
        }

        /** Hack to remove a structure problem in criterion node */
        $rawPost = preg_replace('/<Criterion(\s+)name="(.+?)">(.+?)<\/Criterion>/', '<$2>$3</$2>', $rawPost);
        $this->log('XML Object from Push : ' . $rawPost);

        $xml = simplexml_load_string($rawPost);
        $xmlTransaction = $xml->Transaction;

        // @codingStandardsIgnoreStart simplexml notation
        list($type, $method) = Mage::helper('hcd/payment')
            ->splitPaymentCode((string)$xmlTransaction->Payment['code']);

        if ($method === 'RG') {
            return;
        }

        $hash = (string)$xmlTransaction->Analysis->SECRET;
        $orderID = (string)$xmlTransaction->Identification->TransactionID;
        // @codingStandardsIgnoreEnd

        if (Mage::getModel('hcd/resource_encryption')->validateHash($orderID, $hash) === false) {
            $this->log(
                'Get response form server ' . Mage::app()->getRequest()->getServer('REMOTE_ADDR')
                . ' with an invalid hash. This could be some kind of manipulation.',
                'WARN'
            );
            $this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
            return;
        }

        // @codingStandardsIgnoreStart simplexml notation
        $xmlData = array(
            'PAYMENT_CODE' => (string)$xmlTransaction->Payment['code'],
            'IDENTIFICATION_TRANSACTIONID' => (string)$orderID,
            'IDENTIFICATION_UNIQUEID' => (string)$xmlTransaction->Identification->UniqueID,
            'PROCESSING_RESULT' => (string)$xmlTransaction->Processing->Result,
            'IDENTIFICATION_SHORTID' => (string)$xmlTransaction->Identification->ShortID,
            'PROCESSING_STATUS_CODE' => (string)$xmlTransaction->Processing->Status['code'],
            'PROCESSING_RETURN' => (string)$xmlTransaction->Processing->Return,
            'PROCESSING_RETURN_CODE' => (string)$xmlTransaction->Processing->Return['code'],
            'PRESENTATION_AMOUNT' => (string)$xmlTransaction->Payment->Presentation->Amount,
            'PRESENTATION_CURRENCY' => (string)$xmlTransaction->Payment->Presentation->Currency,
            'IDENTIFICATION_REFERENCEID' => (string)$xmlTransaction->Identification->ReferenceID,
            'CRITERION_STOREID' => (int)$xmlTransaction->Analysis->STOREID,
            'ACCOUNT_BRAND' => false,
            'CRITERION_LANGUAGE' => strtoupper((string)$xmlTransaction->Analysis->LANGUAGE)
        );
        // @codingStandardsIgnoreEnd

        $order = $this->getOrder();
        $order->loadByIncrementId($orderID);
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();

        switch ($paymentCode) {
            case 'hcddd':
                // @codingStandardsIgnoreStart simplexml notation
                $xmlData['CLEARING_AMOUNT'] = (string)$xmlTransaction->Payment->Clearing->Amount;
                $xmlData['CLEARING_CURRENCY'] = (string)$xmlTransaction->Payment->Clearing->Currency;
                $xmlData['ACCOUNT_IBAN'] = (string)$xmlTransaction->Account->Iban;
                $xmlData['ACCOUNT_BIC'] = (string)$xmlTransaction->Account->Bic;
                $xmlData['ACCOUNT_IDENTIFICATION'] = (string)$xmlTransaction->Account->Identification;
                $xmlData['IDENTIFICATION_CREDITOR_ID'] = (string)$xmlTransaction->Identification->CreditorID;
                // @codingStandardsIgnoreEnd
                break;
            case 'hcdbs':
                if ($method === 'FI') {
                    // @codingStandardsIgnoreStart simplexml notation
                    $xmlData['CRITERION_BILLSAFE_LEGALNOTE'] = (string)$xmlTransaction->Analysis->BILLSAFE_LEGALNOTE;
                    $xmlData['CRITERION_BILLSAFE_AMOUNT'] = (string)$xmlTransaction->Analysis->BILLSAFE_AMOUNT;
                    $xmlData['CRITERION_BILLSAFE_CURRENCY'] = (string)$xmlTransaction->Analysis->BILLSAFE_CURRENCY;
                    $xmlData['CRITERION_BILLSAFE_RECIPIENT'] = (string)$xmlTransaction->Analysis->BILLSAFE_RECIPIENT;
                    $xmlData['CRITERION_BILLSAFE_IBAN'] = (string)$xmlTransaction->Analysis->BILLSAFE_IBAN;
                    $xmlData['CRITERION_BILLSAFE_BIC'] = (string)$xmlTransaction->Analysis->BILLSAFE_BIC;
                    $xmlData['CRITERION_BILLSAFE_REFERENCE'] = (string)$xmlTransaction->Analysis->BILLSAFE_REFERENCE;
                    $xmlData['CRITERION_BILLSAFE_PERIOD'] = (string)$xmlTransaction->Analysis->BILLSAFE_PERIOD;
                    $xmlData['ACCOUNT_BRAND'] = 'BILLSAFE';
                    // @codingStandardsIgnoreEnd
                }
                break;
        }

        // @codingStandardsIgnoreLine simplexml notation
        if (!empty($xmlTransaction->Identification->UniqueID)) {
            $lastData = Mage::getModel('hcd/transaction')
                ->loadLastTransactionDataByUniqeId($xmlData['IDENTIFICATION_UNIQUEID']);
        }

        if ($lastData === false) {
            Mage::getModel('hcd/transaction')->saveTransactionData($xmlData, 'push');
        }

        $this->log('PaymentCode ' . $paymentCode);
        $this->log($type . '.' . $method);

        $paymentTransactionTypes = array('CB', 'RC', 'CP', 'DB');
        if (($method === 'FI' && $paymentCode === 'hcdbs') ||
            in_array($method, $paymentTransactionTypes, true)
        ) {
            /** @var  $orderStateHelper HeidelpayCD_Edition_Helper_OrderState */
            $orderStateHelper = Mage::helper('hcd/orderState');
            $orderStateHelper->mapStatus($xmlData, $order);
        }
    }
}
