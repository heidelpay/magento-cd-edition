<?php

/**
 * Abstract payment method
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
class HeidelpayCD_Edition_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @var string payment code of the method
     */
    protected $_code = 'abstract';
    /**
     * @var Mage_Sales_Model_Order magento order object
     */
    protected $_order;
    /**
     * @var bool weather this payment method is a gateway method
     */
    protected $_isGateway = true;
    /**
     * @var bool this payment method allows authorisation
     */
    protected $_canAuthorize = false;
    /**
     * @var bool this payment method is able to capture
     */
    protected $_canCapture = false;
    /**
     * @var bool this payment method is capable of partly capture
     */
    protected $_canCapturePartial = false;
    /**
     * @var bool the chard amount can be reverted to the given account
     */
    protected $_canRefund = true;
    /**
     * @var bool even spear invoice can be reverted
     */
    protected $_canRefundInvoicePartial = true;

    protected $_canVoid = true;
    /**
     * @var bool payment method can be used from backend
     */
    protected $_canUseInternal = false;
    /**
     * @var bool payment method can be used for checkout
     */
    protected $_canUseCheckout = true;
    /**
     * @var bool payment method supports multishipping checkout
     */
    protected $_canUseForMultishipping = false;
    /**
     * @var bool Basket details will be send to the payment server
     */
    protected $_canBasketApi = false;
    /**
     * @var bool payment method needs to be initialized
     */
    protected $_isInitializeNeeded = true;
    /**
     * @var bool invoice order mail send
     */
    protected $_invoiceOrderEmail = true;

    /**
     * @var string productive payment server url
     */
    protected $_liveUrl = 'https://heidelpay.hpcgw.net/ngw/post';
    /**
     * @var string sandbox payment server url
     */
    protected $_sandboxUrl = 'https://test-heidelpay.hpcgw.net/ngw/post';
    /**
     * @var string checkout information and form
     */
    protected $_formBlockType = 'hcd/form_desconly';

    /**
     * @return bool payment method will redirect the customer directly to heidelpay
     */
    public function activeRedirect()
    {
        return true;
    }

    /**
     * Inject template for checkout
     *
     * @return string template form
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * This payment method supports basket api
     *
     * @return bool
     */
    public function canBasketApi()
    {
        return $this->_canBasketApi;
    }

    /**
     * Getter for pending status
     *
     * @param bool $param return state or status
     *
     * @return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
     */
    public function getStatusPendig($param = false)
    {
        if ($param == false) {
            return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        } // status

        return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; //state
    }

    /**
     * Getter for error status
     *
     * @param bool $param return state or status
     *
     * @return Mage_Sales_Model_Order::STATE_CANCELED;
     */
    public function getStatusError($param = false)
    {
        if ($param == false) {
            return Mage_Sales_Model_Order::STATE_CANCELED;
        } // status

        return Mage_Sales_Model_Order::STATE_CANCELED; //state
    }

    /**
     * Getter for success status
     *
     * @param bool $param return state or status
     *
     * @return Mage_Sales_Model_Order::STATE_PROCESSING;
     */
    public function getStatusSuccess($param = false)
    {
        if ($param == false) {
            return Mage_Sales_Model_Order::STATE_PROCESSING;
        } // status

        return Mage_Sales_Model_Order::STATE_PROCESSING; //state
    }

    /**
     * Getter for partly paid status
     *
     * @param bool $param return state or status
     *
     * @return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
     */
    public function getStatusPartlyPaid($param = false)
    {
        if ($param == false) {
            return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
        } // status

        return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW; //state
    }

    /**
     * Getter for core session
     *
     * @return Mage_Heidelpay_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * Validate input data from checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_Abstract
     */
    public function validate()
    {
        parent::validate();
        return $this;
    }

    /**
     * Inject information template
     *
     * @return string
     */
    public function getInfoBlockType()
    {
        return $this->_infoBlockType;
    }

    /**
     * Deactivate payment method in case of wrong currency or other credentials
     *
     * @param Mage_Quote
     * @param null|mixed $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        # Minimum and maximum amount
        $totals = $this->getQuote()->getTotals();
        if (!isset($totals['grand_total'])) {
            return false;
        }

        $storeId = Mage::app()->getStore()->getId();

        $amount = sprintf('%1.2f', $totals['grand_total']->getData('value'));
        $amount = $amount * 100;
        $path = "payment/" . $this->_code . "/";
        $minamount = Mage::getStoreConfig($path . 'min_amount', $storeId);
        $maxamount = Mage::getStoreConfig($path . 'max_amount', $storeId);
        if (is_numeric($minamount) && $minamount > 0 && $minamount > $amount) {
            return false;
        }

        if (is_numeric($maxamount) && $maxamount > 0 && $maxamount < $amount) {
            return false;
        }

        return parent::isAvailable($quote);
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
     *  Get current checkout session
     *
     * @return Mage_Core_Model_Abstract::getSingleton('checkout/session')
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Redirect to heidelpay index controller in case of placing the order
     *
     * @return string controller url
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('hcd/', array('_secure' => true));
    }

    /**
     * Call heidelpay api for a payment request
     *
     * @param bool  $isRegistration payment method supports registration
     * @param bool  $BasketI        Id of a heidelpay basket api call
     * @param bool  $refId          payment reference id for debit/authorize on a registration
     * @param mixed $basketId
     *
     * @return mixed
     */
    public function getHeidelpayUrl($isRegistration = false, $basketId = false, $refId = false)
    {
        $config = $frontend = $user = $basketData = array();
        $criterion = array();

        if ($isRegistration === false) {
            $order = Mage::getModel('sales/order');
            $session = $this->getCheckout();
            $order->loadByIncrementId($session->getLastRealOrderId());
            $ordernr = $order->getRealOrderId();
        } else {
            $customerId = $this->getCustomerId();
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $ordernr = ($customerId == 0) ? $visitorData['visitor_id'] : $customerId;
            $order = $this->getQuote();
        }

        $this->log("Heidelpay Payment Code : " . $this->_code);
        $config = $this->getMainConfig($this->_code);
        if ($isRegistration === true) {
            $config['PAYMENT.TYPE'] = 'RG';
        }

        if ($isRegistration === true) {
            $basketData['PRESENTATION.CURRENCY'] =
                $this->getQuote()->getQuoteCurrencyCode();
        }

        // add parameters for pci 3 iframe

        if ($this->_code == 'hcdcc' or $this->_code == 'hcddc') {
            $url = explode('/', Mage::getUrl('/', array('_secure' => true)));
            $criterion['FRONTEND.PAYMENT_FRAME_ORIGIN'] = $url[0] . '//' . $url[2];
            $criterion['FRONTEND.CSS_PATH'] =
                Mage::getDesign()->getSkinUrl(
                    'css/' . $this->_code . '_payment_frame.css',
                    array('_secure' => true)
                );
            // set frame to sync modus if frame is used in befor order mode
            // (this is the registration case)
            $criterion['FRONTEND.PREVENT_ASYNC_REDIRECT'] =
                ($isRegistration === true) ? 'TRUE' : 'FALSE';
        }

        $frontend = $this->getFrontend($ordernr);
        if ($isRegistration === true) {
            $frontend['FRONTEND.SUCCESS_URL'] =
                Mage::getUrl('hcd/', array('_secure' => true));
        }

        if ($isRegistration === true) {
            $frontend['CRITERION.SHIPPPING_HASH'] = $this->getShippingHash();
        }

        $user = $this->getUser($order, $isRegistration);


        if ($isRegistration === false) {
            $completeBasket =
                ($config['INVOICEING'] == 1
                    or $this->_code == "hcdbs") ? true : false;
            $basketData = $this->getBasketData($order, $completeBasket);
        }

        if ($refId !== false) {
            $user['IDENTIFICATION.REFERENCEID'] = $refId;
        }

        if ($basketId !== false) {
            $basketData['BASKET.ID'] = $basketId;
        }

        Mage::dispatchEvent(
            'heidelpay_getHeidelpayUrl_bevor_preparePostData',
            array(
                'order' => $order,
                'config' => $config,
                'frontend' => $frontend,
                'user' => $user,
                'basketData' => $basketData,
                'criterion' => $criterion
            )
        );

        $params = Mage::helper('hcd/payment')->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion
        );
        $this->log("doRequest url : " . $config['URL'], 'DEBUG');
        $this->log("doRequest params : " . json_encode($params), 'DEBUG');
        $src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
        $this->log("doRequest response : " . json_encode($src), 'DEBUG');

        return $src;
    }

    /**
     * Getter for customer identification number
     *
     * @return int customer identification number
     */
    public function getCustomerId()
    {
        return $this->getQuote()->getBillingAddress()->getCustomerId();
    }

    /**
     * logger
     *
     * @param $message string message that should be logged
     * @param string $level message level (like debug,info or warning)
     * @param bool   $file  name of the logfile
     *
     * @return mixed
     */
    public function log($message, $level = "DEBUG", $file = false)
    {
        $callers = debug_backtrace();
        $paymentHelper = Mage::helper('hcd/payment');
        return $paymentHelper->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }

    /**
     * Load configuration parameter for the given payment method
     *
     * @param mixed $code    payment method code
     * @param mixed $storeId magento store identification number
     *
     * @return mixed
     */
    public function getMainConfig($code, $storeId = false)
    {
        $storeId = ($storeId) ? $storeId : $this->getStore();
        $path = "hcd/settings/";
        $config = array();
        $config['PAYMENT.METHOD'] = preg_replace('/^hcd/', '', $code);
        $config['SECURITY.SENDER'] = Mage::getStoreConfig($path . "security_sender", $storeId);
        if (Mage::getStoreConfig($path . "transactionmode", $storeId) == 0) {
            $config['TRANSACTION.MODE'] = 'LIVE';
            $config['URL'] = $this->_liveUrl;
        } else {
            $config['TRANSACTION.MODE'] = 'CONNECTOR_TEST';
            $config['URL'] = $this->_sandboxUrl;
        }

        $config['USER.LOGIN'] = trim(Mage::getStoreConfig($path . "user_id", $storeId));
        $config['USER.PWD'] = trim(Mage::getStoreConfig($path . "user_pwd", $storeId));
        $config['INVOICEING'] = (Mage::getStoreConfig($path . "invoicing", $storeId) == 1) ? 1 : 0;
        $config['USER.PWD'] = trim(Mage::getStoreConfig($path . "user_pwd", $storeId));

        $path = "payment/" . $code . "/";
        $config['TRANSACTION.CHANNEL'] = trim(Mage::getStoreConfig($path . "channel", $storeId));
        (Mage::getStoreConfig(
            $path . "bookingmode",
            $storeId
        ) == true) ? $config['PAYMENT.TYPE'] = Mage::getStoreConfig(
            $path . "bookingmode",
            $storeId
        ) : false;

        return $config;
    }

    /**
     * Prepare frontend parameter for heidelpay api call
     *
     * @param $orderNumber order identification number
     * @param bool $storeId shore identification number
     *
     * @return array
     */
    public function getFrontend($orderNumber, $storeId = false)
    {
        return array(
            'FRONTEND.LANGUAGE' => Mage::helper('hcd/payment')->getLang(),
            'FRONTEND.RESPONSE_URL' => Mage::getUrl(
                'hcd/response/',
                array('_forced_secure' => true,'_store_to_url' => true,'_nosid' => true)
            ),
            'FRONTEND.SUCCESS_URL' => Mage::getUrl(
                'hcd/index/success',
                array('_forced_secure' => true,'_store_to_url' => true,'_nosid' => true)
            ),
            'FRONTEND.FAILURE_URL' => Mage::getUrl(
                'hcd/index/error',
                array('_forced_secure' => true,'_store_to_url' => true,'_nosid' => true)
            ),
            'CRITERION.PUSH_URL' => Mage::getUrl(
                'hcd/index/push',
                array('_forced_secure' => true,'_store_to_url' => true,'_nosid' => true)
            ),
            'CRITERION.SECRET' => Mage::getModel('hcd/resource_encryption')
                    ->getHash((string)$orderNumber),
            'CRITERION.LANGUAGE' => strtolower(Mage::helper('hcd/payment')->getLang()),
            'CRITERION.STOREID' => ($storeId) ? $storeId : Mage::app()->getStore()->getId(),
            'SHOP.TYPE' => 'Magento ' . Mage::getVersion(),
            'SHOPMODULE.VERSION' => 'HeidelpayCD Edition - ' .
                (string)Mage::getConfig()->getNode()->modules->HeidelpayCD_Edition->version
        );
    }

    /**
     * ShippingHash Getter
     *
     * A hash of the customers shipping details
     *
     * @return string hash
     *
     */
    public function getShippingHash()
    {
        $shipping = $this->getQuote()->getShippingAddress();
        // @codingStandardsIgnoreStart can not be replace because of backwards compatiblity
        return md5(
            $shipping->getFirstname() .
            $shipping->getLastname() .
            $shipping->getStreet1() . " " . $shipping->getStreet2() .
            $shipping->getPostcode() .
            $shipping->getCity() .
            $shipping->getCountry()
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Customer parameter for heidelpay api call
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $isReg in case of registration
     *
     * @return array
     *
     */
    // @codingStandardsIgnoreLine should be refactored - issue #1
    public function getUser($order, $isReg = false)
    {
        $user = array();
        $billing = $order->getBillingAddress();
        $email = ($order->getBillingAddress()->getEmail())
            ? $order->getBillingAddress()->getEmail() : $order->getCustomerEmail();
        $customerId = $billing->getCustomerId();
        $user['CRITERION.GUEST'] = 'false';
        if ($customerId == 0) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $user['CRITERION.GUEST'] = 'true';
        }

        $user['IDENTIFICATION.SHOPPERID'] = $customerId;
        if ($billing->getCompany() == true) {
            $user['NAME.COMPANY'] = trim($billing->getCompany());
        }

        $user['NAME.GIVEN'] = trim($billing->getFirstname());
        $user['NAME.FAMILY'] = trim($billing->getLastname());
        $user['ADDRESS.STREET'] =
            trim($billing->getStreet1() . " " . $billing->getStreet2());
        $user['ADDRESS.ZIP'] = trim($billing->getPostcode());
        $user['ADDRESS.CITY'] = trim($billing->getCity());
        $user['ADDRESS.COUNTRY'] = trim($billing->getCountry());
        $user['CONTACT.EMAIL'] = trim($email);
        $user['CONTACT.IP'] =
            (filter_var(
                trim(Mage::app()->getRequest()->getClientIp()), FILTER_VALIDATE_IP
            )) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1';


        //load recognized data

        if ($isReg === false
            and $order->getPayment()->getMethodInstance()
                ->activeRedirect() === true
        ) {
            if ($this->getCustomerData($this->_code, $billing->getCustomerId())) {
                $paymentData =
                    $this->getCustomerData($this->_code, $billing->getCustomerId());


                $this->log('getUser Customer: ' . json_encode($paymentData), 'DEBUG');

                if (isset($paymentData['payment_data']['ACCOUNT.IBAN'])) {
                    $paymentData['payment_data']['ACCOUNT.IBAN'] =
                        strtoupper($paymentData['payment_data']['ACCOUNT.IBAN']);
                }

                // remove SHIPPING_HASH from parameters
                if (isset($paymentData['payment_data']['SHIPPPING_HASH'])) {
                    unset($paymentData['payment_data']['SHIPPPING_HASH']);
                }

                // remove cc or dc reference data
                if ($this->_code == 'hcdcc' or $this->_code == 'hcddc') {
                    if (isset($paymentData['payment_data']['ACCOUNT_BRAND'])) {
                        unset($paymentData['payment_data']['ACCOUNT_BRAND']);
                    }

                    if (isset($paymentData['payment_data']['ACCOUNT_NUMBER'])) {
                        unset($paymentData['payment_data']['ACCOUNT_NUMBER']);
                    }

                    if (isset($paymentData['payment_data']['ACCOUNT_HOLDER'])) {
                        unset($paymentData['payment_data']['ACCOUNT_HOLDER']);
                    }

                    if (isset($paymentData['payment_data']['ACCOUNT_EXPIRY_MONTH'])
                    ) {
                        unset($paymentData['payment_data']['ACCOUNT_EXPIRY_MONTH']);
                    }

                    if (isset($paymentData['payment_data']['ACCOUNT_EXPIRY_YEAR'])) {
                        unset($paymentData['payment_data']['ACCOUNT_EXPIRY_YEAR']);
                    }
                }

                foreach ($paymentData['payment_data'] as $k => $v) {
                    $user[$k] = $v;
                }
            }
        }

        return $user;
    }

    /**
     * Load additional payment information
     *
     * @param bool $code       current payment method
     * @param bool $customerId the customers identification number
     * @param bool $storeId    magento store id
     *
     * @return array|bool additional payment information
     */
    public function getCustomerData($code = false, $customerId = false, $storeId = false)
    {
        $paymentCode = ($code) ? $code : $this->_code;
        $customerId = ($customerId) ? $customerId : $this->getQuote()->getBillingAddress()->getCustomerId();
        $storeId = ($storeId) ? $storeId : Mage::app()->getStore()->getId();
        if ($customerId == 0) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $storeId = 0;
        }

        $this->log('StoreID :' . Mage::app()->getStore()->getId());

        $customerData = Mage::getModel('hcd/customer')
            ->getCollection()
            ->addFieldToFilter('Customerid', $customerId)
            ->addFieldToFilter('Storeid', $storeId)
            ->addFieldToFilter('Paymentmethode', $paymentCode);

        $customerData->load();
        $data = $customerData->getData();

        /* retun false if not */
        if (empty($data[0]['id'])) {
            return false;
        }

        $return = array();

        $return['id'] = $data[0]['id'];

        if (!empty($data[0]['uniqeid'])) {
            $return['uniqeid'] = $data[0]['uniqeid'];
        }

        if (!empty($data[0]['payment_data'])) {
            $return['payment_data'] = json_decode(
                Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['payment_data']),
                true
            );
        }

        return $return;
    }

    /**
     * Prepare basket details for heidelpay basket call
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $completeBasket
     * @param bool $amount         order amount
     *
     * @return array
     */
    public function getBasketData($order, $completeBasket = false, $amount = false)
    {
        $data = array(
            'PRESENTATION.AMOUNT' => ($amount) ? $amount : Mage::helper('hcd/payment')
                    ->format($order->getGrandTotal()),
            'PRESENTATION.CURRENCY' => $order->getOrderCurrencyCode(),
            'IDENTIFICATION.TRANSACTIONID' => $order->getRealOrderId()
        );
        // Add basket details in case of BillSafe or invoicing over heidelpay
        $basket = array();
        if ($completeBasket) {
            $basket = $this->getBasket($order);
        }

        return array_merge($basket, $data);
    }

    /**
     * Getter for the payment method backend title
     *
     * @return string payment method title
     */
    public function getAdminTitle()
    {
        return $this->getTitle();
    }

    /**
     * Getter for the payment method frontend title
     *
     * @return string payment method title
     */
    public function getTitle()
    {
        $storeId = $this->getStore();
        $path = "payment/" . $this->_code . "/";
        return $this->_getHelper()->__(Mage::getStoreConfig($path . "title", $storeId));
    }

    /**
     * Api call to capture a given amount on an invoice
     *
     * @param $payment Mage_Sales_Model_Order_Payment current payment object
     * @param $amount float amount to capture
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $criterion = array();

        $order = $payment->getOrder();
        $this->log('StoreId' . $order->getStoreId());
        if ($this->canCapture()) {
            $authorisationModel = Mage::getModel('hcd/transaction');
            /**
             * @var $authorisation HeidelpayCD_Edition_Model_Transaction
             */
            $authorisation = $authorisationModel->getOneTransactionByMethode(
                $order->getRealOrderId(),
                'PA'
            );


            if ($authorisation === false) {
                Mage::throwException(Mage::helper('hcd')->__('This Transaction could not be capture online.'));
                return $this;
            }

            $config = $this->getMainConfig($this->_code, $authorisation['CRITERION_STOREID']);
            $config['PAYMENT.TYPE'] = 'CP';


            $frontend = $this->getFrontend($order->getRealOrderId(), $authorisation['CRITERION_STOREID']);
            $frontend['FRONTEND.MODE'] = 'DEFAULT';
            $frontend['FRONTEND.ENABLED'] = 'false';

            $user = $this->getUser($order, true);
            $basketdetails = ($this->_code == 'hcdbs') ? true : false; // If billsafe set to fin
            $basketData = $this->getBasketData($order, $basketdetails, $amount);

            $basketData['IDENTIFICATION.REFERENCEID'] = $authorisation['IDENTIFICATION_UNIQUEID'];
            Mage::dispatchEvent(
                'heidelpay_capture_bevor_preparePostData', array(
                'payment' => $payment,
                'config' => $config,
                'frontend' => $frontend,
                'user' => $user,
                'basketData' => $basketData,
                'criterion' => $criterion
                )
            );
            $params = Mage::helper('hcd/payment')->preparePostData(
                $config, $frontend, $user, $basketData,
                $criterion
            );


            $this->log("doRequest url : " . $config['URL']);
            $this->log("doRequest params : " . json_encode($params));

            $src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);

            $this->log("doRequest response : " . json_encode($src));



            if ($src['PROCESSING_RESULT'] == "NOK") {
                Mage::throwException('Heidelpay Error: ' . $src['PROCESSING_RETURN']);
                return $this;
            }

            $payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
            Mage::getModel('hcd/transaction')->saveTransactionData($src);
        }

        return $this;
    }

    /**
     *  Calculate weather a order can be captured or not
     *
     * @return bool canCapture
     */
    public function canCapture()
    {
        //check weather this payment method supports capture

        if ($this->_canCapture === false) {
            return false;
        }

        // prevent frontend to capture an amount in case of direct booking with automatical invoice
        if (Mage::app()->getStore()->getId() != 0) {
            $this->log('try to capture amount in frontend ... this is not necessary !');
            return false;
        }

        // loading order object to check wether this
        $orderIncrementId = Mage::app()->getRequest()->getParam('order_id');
        $this->log('$orderIncrementId ' . $orderIncrementId);
        /**
         * @var $order Mage_Sales_Model_Order order object
         */
        $order = Mage::getModel('sales/order');
        $order->loadByAttribute('entity_id', (int)$orderIncrementId);
        /**
         * @var $transaction HeidelpayCD_Edition_Model_Transaction
         */
        $transaction = Mage::getModel('hcd/transaction');
        if ($transaction->getOneTransactionByMethode($order->getRealOrderId(), 'PA') === false) {
            $this->log('there is no preauthorisation for the order ' . $order->getRealOrderId());
            return false;
        }

        return true;
    }

    /**
     * Api call for refunding a given invoice
     *
     * @param Mage_Sales_Model_Order_Payment $payment current payment object
     * @param float                          $amount  amount to refund
     *
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        /** @var $order Mage_Sales_Model_Order */
        $order = $payment->getOrder();

        /**  @var $transaction HeidelpayCD_Edition_Model_Transaction */
        $transaction = Mage::getModel('hcd/transaction');
        $captureData = $transaction->loadLastTransactionDataByUniqeId((string)$payment->getRefundTransactionId());

        $config = $this->getMainConfig($this->_code, $captureData['CRITERION_STOREID']);
        $config['PAYMENT.TYPE'] = 'RF';
        $frontend = $this->getFrontend($order->getRealOrderId(), $captureData['CRITERION_STOREID']);
        $frontend['FRONTEND.MODE'] = 'DEFAULT';
        $frontend['FRONTEND.ENABLED'] = 'false';
        $user = $this->getUser($order, true);
        $basketData = $this->getBasketData($order, false, $amount);
        $basketData['IDENTIFICATION.REFERENCEID'] = (string)$payment->getRefundTransactionId();
        $params = Mage::helper('hcd/payment')->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion = array()
        );
        $this->log("doRequest url : " . $config['URL']);
        $this->log("doRequest params : " . json_encode($params));

        $src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
        $this->log("doRequest response : " . json_encode($src));
        if ($src['PROCESSING_RESULT'] == "NOK") {
            Mage::throwException('Heidelpay Error: ' . $src['PROCESSING_RETURN']);
            return $this;
        }

        $payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
        Mage::getModel('hcd/transaction')->saveTransactionData($src);
        return $this;
    }

    /**
     * Getter for customer given plus family name
     *
     * @param $session Mage_Checkout_Model_Session checkout session
     *
     * @return string given plus family name
     */
    public function getCustomerName($session = false)
    {
        if ($session === true) {
            $session = $this->getCheckout();
            return $session->getQuote()->getBillingAddress()->getFirstname() . ' '
                . $session->getQuote()->getBillingAddress()->getLastname();
        }

        return $this->getQuote()->getBillingAddress()->getFirstname() . ' '
            . $this->getQuote()->getBillingAddress()->getLastname();
    }

    /**
     * Save additional payment data of the customer to the database
     *
     * @param $data array additional payment information of the customer
     * @param $uniqeID string payment reference of a account registration
     */
    public function saveCustomerData($data, $uniqeID = null)
    {
        /** @var $customerData HeidelpayCD_Edition_Model_Customer */
        $customerData = Mage::getModel('hcd/customer');

        if ($this->getCustomerData() !== false) {
            $lastdata = $this->getCustomerData();
            $customerData->load($lastdata['id']);
        }

        $this->log('StoreID :' . Mage::app()->getStore()->getId());
        $customerId = $this->getQuote()->getBillingAddress()->getCustomerId();
        $storeId = Mage::app()->getStore()->getId();
        if ($customerId == 0) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $storeId = 0;
        }

        $customerData->setPaymentmethode($this->_code);
        $customerData->setUniqeid($uniqeID);
        $customerData->setCustomerid($customerId);
        $customerData->setStoreid($storeId);
        $data['SHIPPPING_HASH'] = $this->getShippingHash();
        $customerData->setPaymentData(Mage::getModel('hcd/resource_encryption')->encrypt(json_encode($data)));

        $customerData->save();
    }

    /**
     * Generates a customer message for the success page
     *
     * Will be used for prepayment and direct debit to show the customer
     * the billing information
     *
     * @param $paymentData transaction details form heidelpay api
     *
     * @return bool| string  customer message for the success page
     */
    public function showPaymentInfo($paymentData)
    {
        /*
         * This function should not be modified please overwrite this function
         * in the class of the used payment method !!!
         */
        return false;
    }

    /**
     * Handle charge back notices from heidelpay payment
     *
     * @param $order Mage_Sales_Model_Order
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function chargeBackTransaction($order, $message="")
    {
        // message block for direct debit charge back
        if ($order->hasInvoices()) {
            $invIncrementIDs = array();
            /** @var  $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($order->getInvoiceCollection() as $invoice) {
                $this->log('Invoice Number ' . (string)$invoice->getIncrementId());
                $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                $invoice->setIsPaid(false);
                // @codingStandardsIgnoreLine use of save in a loop
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

    /**
     * Handle transaction with an not ok status
     *
     * @param $order Mage_Sales_Model_Order
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function canceledTransaction($order, $message)
    {
        if ($order->canCancel()) {
            $order->cancel();

            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusError(false),
                $order->getPayment()->getMethodInstance()->getStatusError(true),
                $message
            );
        }

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
    public function processingTransaction($order, $data, $message='')
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
            /*
             * in case rc is ack and amount is to low or currency miss match
             */
            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(false),
                $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(true),
                $message
            );
        }

        $code = $order->getPayment()->getMethodInstance()->getCode();

        $path = "payment/" . $code . "/";
        $autoInvoice = (Mage::getStoreConfig($path . "invioce", $data['CRITERION_STOREID']) == 1) ? true : false;

        $this->log('Auto invoiced :'. (string) $autoInvoice);

        if ($order->canInvoice() and ($autoInvoice or $code == 'hcdbs') and $totallyPaid === true) {
            $invoice = $order->prepareInvoice();
            $invoice->register()->capture();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
            $invoice->setIsPaid(true);
            $order->setIsInProcess(true);
            $order->addStatusHistoryComment(
                Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'),
                false
            );
            $invoice->save();

            if ($this->_invoiceOrderEmail) {
                if ($code != 'hcdpp' and $code != 'hcdiv') {
                    $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
                    $invoiceMailComment = ($info === false) ? '' : '<h3>'
                        . $this->_getHelper()->__('Payment Information') . '</h3>' . $info . '<br/>';
                }

                $invoice->sendEmail(true, $invoiceMailComment); // send invoice mail
            }


            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
        }

        $order->getPayment()->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            null,
            true,
            $message
        );

        $order->setIsInProcess(true);

        return $order;
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
    public function pendingTransaction($order, $data, $message='')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] .' '.$message;

        $order->getPayment()->setTransactionId($data['IDENTIFICATION_UNIQUEID']);

        $order->getPayment()->setIsTransactionClosed(0);

        $order->getPayment()->setTransactionAdditionalInfo(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            null
        );

        $this->log('Set Transaction to Pending : ');
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
}
