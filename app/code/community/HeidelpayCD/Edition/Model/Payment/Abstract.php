<?php

/**
 * Abstract payment method
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
class HeidelpayCD_Edition_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @var Mage_Sales_Model_Order magento order object
     */
    protected $_order;

    /**
     * @var bool Basket details will be send to the payment server
     */
    protected $_canBasketApi = false;

    /**
     * @var bool Determines if the payment method supports reversal transactions
     */
    protected $_canReversal = false;

    /**
     * @var bool invoice order mail send
     */
    protected $_invoiceOrderEmail = true;

    /**
     * @var bool
     */
    protected $_reportsShippingToHeidelpay = false;

    /**
     * Append invoice info text to customer email.
     *
     * @var bool $_sendsInvoiceMailComment
     */
    protected $_sendsInvoiceMailComment = false;

    /**
     * @var string productive payment server url
     */
    protected $_liveUrl = 'https://heidelpay.hpcgw.net/ngw/post';

    /**
     * @var string sandbox payment server url
     */
    protected $_sandboxUrl = 'https://test-heidelpay.hpcgw.net/ngw/post';

    /**
     * Indicates whether to show payment information after successful checkout (e.g. remittance data).
     *
     * @var bool $_showAdditionalPaymentInformation
     */
    protected $_showAdditionalPaymentInformation = false;

    /**
     * HeidelpayCD_Edition_Model_Payment_Abstract constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'abstract';
        $this->_isGateway = true;
        $this->_canAuthorize = false;
        $this->_canCapture = false;
        $this->_canCapturePartial = false;
        $this->_canRefund = true;
        $this->_canRefundInvoicePartial = true;
        $this->_canVoid = true;
        $this->_canUseInternal = false;
        $this->_canUseCheckout = true;
        $this->_canUseForMultishipping = false;
        $this->_isInitializeNeeded = true;
        $this->_formBlockType = 'hcd/form_desconly';
    }

    /**
     * @return bool payment method will redirect the customer directly to heidelpay
     */
    public function activeRedirect()
    {
        return true;
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
     * @return bool
     */
    public function canInvoiceOrderEmail()
    {
        return $this->_invoiceOrderEmail;
    }

    /**
     * Returns if the payment method supports reversal transactions (cancel invoice/order)
     *
     * @return bool
     */
    public function canReversal()
    {
        return $this->_canReversal;
    }

    /**
     * Getter for pending status
     *
     * @param bool $param return state or status
     *
     * @return string
     */
    public function getStatusPending($param = false)
    {
        if (!$param) {
            return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; // status
        }

        return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; // state
    }

    /**
     * Getter for error status
     *
     * @param bool $param return state or status
     *
     * @return string
     */
    public function getStatusError($param = false)
    {
        if (!$param) {
            return Mage_Sales_Model_Order::STATE_CANCELED; // status
        }

        return Mage_Sales_Model_Order::STATE_CANCELED; // state
    }

    /**
     * Getter for success status
     *
     * @param bool $param return state or status
     *
     * @return string
     */
    public function getStatusSuccess($param = false)
    {
        if (!$param) {
            return Mage_Sales_Model_Order::STATE_PROCESSING; // status
        }

        return Mage_Sales_Model_Order::STATE_PROCESSING; // state
    }

    /**
     * Getter for partly paid status
     *
     * @param bool $param return state or status
     *
     * @return string
     */
    public function getStatusPartlyPaid($param = false)
    {
        if (!$param) {
            return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW; // status
        }

        return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW; // state
    }

    /**
     * Getter for core session
     *
     * @return Mage_Core_Model_Session|Mage_Core_Model_Abstract
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
     * Show additional payment information for this payment method after checkout.
     *
     * @return bool
     */
    public function isShowAdditionalPaymentInformation()
    {
        return $this->_showAdditionalPaymentInformation;
    }

    /**
     * @inheritdoc
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function isAvailable($quote = null)
    {
        # Minimum and maximum amount
        $totals = $this->getQuote()->getTotals();
        if (!isset($totals['grand_total'])) {
            return false;
        }

        $storeId = $this->getStoreId();

        $amount = sprintf('%1.2f', $totals['grand_total']->getData('value'));
        $amount *= 100;
        $path = 'payment/' . $this->getCode() . '/';
        $minAmount = Mage::getStoreConfig($path . 'min_amount', $storeId);
        $maxAmount = Mage::getStoreConfig($path . 'max_amount', $storeId);
        if (is_numeric($minAmount) && $minAmount > 0 && $minAmount > $amount) {
            return false;
        }

        if (is_numeric($maxAmount) && $maxAmount > 0 && $maxAmount < $amount) {
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
     * @param mixed $basketId
     * @param bool  $refId          payment reference id for debit/authorize on a registration
     *
     * @return mixed
     *
     * @throws \Mage_Core_Exception
     * @throws \Mage_Core_Model_Store_Exception
     *
     * @internal param bool $BasketI Id of a heidelpay basket api call
     *
     * @throws Zend_Http_Client_Exception
     */
    public function getHeidelpayUrl($isRegistration = false, $basketId = false, $refId = false)
    {
        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        $basketData = array();
        $criterion = array();

        if ($isRegistration === false) {
            $order = Mage::getModel('sales/order');
            $session = $this->getCheckout();
            $order->loadByIncrementId($session->getLastRealOrderId());
            $orderNr = $order->getRealOrderId();
        } else {
            $customerId = $this->getCustomerId();
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $orderNr = empty($customerId) ? $visitorData['visitor_id'] : $customerId;
            $order = $this->getQuote();
        }

        $code = $this->getCode();
        $this->log('Heidelpay Payment Code : ' . $code);
        $config = $this->getMainConfig($code);
        if ($isRegistration === true) {
            $config['PAYMENT.TYPE'] = 'RG';
        }

        if ($isRegistration === true) {
            $basketData['PRESENTATION.AMOUNT'] = $paymentHelper
                ->format($this->getQuote()->getGrandTotal());
            $basketData['PRESENTATION.CURRENCY'] = $this->getQuote()->getQuoteCurrencyCode();
        }

        // add parameters for pci 3 iframe
        // todo: use flags not code
        if ($code === 'hcdcc' || $code === 'hcddc') {
            $url = explode('/', Mage::getUrl('/', array('_secure' => true)));
            $criterion['FRONTEND.PAYMENT_FRAME_ORIGIN'] = $url[0] . '//' . $url[2];
            $criterion['FRONTEND.CSS_PATH'] =
                Mage::getDesign()->getSkinUrl(
                    'css/' . $code . '_payment_frame.css',
                    array('_secure' => true)
                );
            // set frame to sync mode if frame is used in before order mode
            // (this is the registration case)
            $criterion['FRONTEND.PREVENT_ASYNC_REDIRECT'] =
                ($isRegistration === true) ? 'TRUE' : 'FALSE';
        }

        $frontend = $this->getFrontend($orderNr);
        if ($isRegistration === true) {
            $frontend['FRONTEND.SUCCESS_URL'] =
                Mage::getUrl('hcd/', array('_secure' => true));
        }

        if ($isRegistration === true) {
            $frontend['CRITERION.SHIPPING_HASH'] = $this->getShippingHash();
        }

        $user = $this->getUser($order, $isRegistration);


        if (!$isRegistration) {
            // todo: flag instead of code
            $completeBasket =
                ($config['INVOICEING'] == 1 || $code === 'hcdbs');
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

        $params = $paymentHelper->preparePostData($config, $frontend, $user, $basketData, $criterion);
        ksort($params);

        $this->log('doRequest url : ' . $config['URL']);
        $this->log('doRequest params : ' . json_encode($params));
        $src = $paymentHelper->doRequest($config['URL'], $params);
        ksort($src);
        $this->log('doRequest response : ' . json_encode($src));

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
     *
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
     * Load configuration parameter for the given payment method
     *
     * @param mixed $code    payment method code
     * @param mixed $storeId magento store identification number
     *
     * @return mixed
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getMainConfig($code, $storeId = false)
    {
        $storeId = $storeId ?: $this->getStoreId();
        $path = 'hcd/settings/';
        $config = array();
        $config['PAYMENT.METHOD'] = preg_replace('/^hcd/', '', $code);
        $config['SECURITY.SENDER'] = Mage::getStoreConfig($path . 'security_sender', $storeId);
        if (Mage::getStoreConfig($path . 'transactionmode', $storeId) == 0) {
            $config['TRANSACTION.MODE'] = 'LIVE';
            $config['URL'] = $this->_liveUrl;
        } else {
            $config['TRANSACTION.MODE'] = 'CONNECTOR_TEST';
            $config['URL'] = $this->_sandboxUrl;
        }

        $config['USER.LOGIN'] = trim(Mage::getStoreConfig($path . 'user_id', $storeId));
        $config['USER.PWD'] = trim(Mage::getStoreConfig($path . 'user_pwd', $storeId));
        $config['INVOICEING'] = (Mage::getStoreConfig($path . 'invoicing', $storeId) == 1) ? 1 : 0;
        $config['USER.PWD'] = trim(Mage::getStoreConfig($path . 'user_pwd', $storeId));

        $path = 'payment/' . $code . '/';
        $config['TRANSACTION.CHANNEL'] = trim(Mage::getStoreConfig($path . 'channel', $storeId));

        if (Mage::getStoreConfig($path . 'bookingmode', $storeId)) {
            $config['PAYMENT.TYPE'] = Mage::getStoreConfig($path . 'bookingmode', $storeId);
        }

        return $config;
    }

    /**
     * Prepare frontend parameter for heidelpay api call
     *
     * @param integer $orderNumber order identification number
     * @param boolean $storeId     shore identification number
     *
     * @return array
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function getFrontend($orderNumber, $storeId = false)
    {
        $frontendLanguage = Mage::helper('hcd/payment')->getLang();
        return array(
            'FRONTEND.LANGUAGE' => $frontendLanguage,
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
            'CRITERION.LANGUAGE' => strtolower($frontendLanguage),
            'CRITERION.STOREID' => $storeId ?: $this->getStoreId(),
            'SHOP.TYPE' => sprintf('Magento %s %s', Mage::getEdition(), Mage::getVersion()),
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
        // @codingStandardsIgnoreStart can not be replace because of backwards compatibility
        return md5(
            $shipping->getFirstname() .
            $shipping->getLastname() .
            $shipping->getStreet1() . ' ' . $shipping->getStreet2() .
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
     * @throws \Mage_Core_Exception
     *
     */
    // @codingStandardsIgnoreLine should be refactored - issue #1
    public function getUser($order, $isReg = false)
    {
        $user = array();
        $billing = $order->getBillingAddress();
        $email = $order->getBillingAddress()->getEmail() ?: $order->getCustomerEmail();
        $phone = $order->getBillingAddress()->getTelephone();

        $customerId = $billing->getCustomerId();
        $user['CRITERION.GUEST'] = 'false';
        if (empty($customerId)) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $user['CRITERION.GUEST'] = 'true';
        }

        $user['IDENTIFICATION.SHOPPERID'] = $customerId;
        if ($billing->getCompany()) {
            $user['NAME.COMPANY'] = trim($billing->getCompany());
        }

        $user['NAME.GIVEN'] = trim($billing->getFirstname());
        $user['NAME.FAMILY'] = trim($billing->getLastname());
        $user['ADDRESS.STREET'] =
            trim($billing->getStreet1() . ' ' . $billing->getStreet2());
        $user['ADDRESS.ZIP'] = trim($billing->getPostcode());
        $user['ADDRESS.CITY'] = trim($billing->getCity());
        $user['ADDRESS.COUNTRY'] = trim($billing->getCountry());
        $user['CONTACT.EMAIL'] = trim($email);
        $user['CONTACT.IP'] =
            filter_var(
                trim(Mage::app()->getRequest()->getClientIp()), FILTER_VALIDATE_IP
            ) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1';

        if (!empty($phone)) {
            $user['CONTACT.PHONE'] = $phone;
        }

        // load recognized data
        $code = $this->getCode();
        if (!$isReg && $this->getCustomerData($code, $billing->getCustomerId()) &&
            $order->getPayment()->getMethodInstance()->activeRedirect()) {
            $paymentData = $this->getCustomerData($code, $billing->getCustomerId());

            $this->log('getUser Customer: ' . json_encode($paymentData));

            if (isset($paymentData['payment_data']['ACCOUNT.IBAN'])) {
                $paymentData['payment_data']['ACCOUNT.IBAN'] =
                    strtoupper($paymentData['payment_data']['ACCOUNT.IBAN']);
            }

            // remove SHIPPING_HASH from parameters
            if (isset($paymentData['payment_data']['SHIPPING_HASH'])) {
                unset($paymentData['payment_data']['SHIPPING_HASH']);
            }

            // remove cc or dc reference data
            // todo: flags instead of code
            if ($code === 'hcdcc' || $code === 'hcddc') {
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

        return $user;
    }

    /**
     * Load additional payment information
     *
     * @param string|null $code       current payment method
     * @param int|null    $customerId the customers identification number
     * @param int|null    $storeId    magento store id
     *
     * @return array additional payment information
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getCustomerData($code = null, $customerId = null, $storeId = null)
    {
        $result = array();

        try {
            $paymentCode = $code ?: $this->getCode();
            $customerId = $customerId ?: $this->getQuote()->getBillingAddress()->getCustomerId();
            $storeId = $storeId ?: $this->getStoreId();
        } catch (Mage_Core_Model_Store_Exception $e) {
            $message = sprintf(
                '%s exception thrown. Message: %s, Code: %s, Stacktrace: %s',
                get_class($e),
                $e->getMessage(),
                $e->getCode(),
                $e->getTraceAsString()
            );
            $this->log($message);
            return $result;
        }

        if (empty($customerId)) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $storeId = 0;
        }

        $this->log(
            sprintf('PaymentCode: %s, Customer-ID: %d, Store-ID: %d', $paymentCode, $customerId, $storeId)
        );

        /** @var HeidelpayCD_Edition_Model_Customer $customerData */
        $customerData = Mage::getModel('hcd/customer')
            ->getCollection()
            ->addFieldToFilter('Customerid', $customerId)
            ->addFieldToFilter('Storeid', $storeId)
            ->addFieldToFilter('Paymentmethode', $paymentCode);

        $customerData->load();
        $data = $customerData->getData();

        /* return empty array if no customer data is present */
        if (empty($data[0]['id'])) {
            return $result;
        }

        $result['id'] = $data[0]['id'];

        if (!empty($data[0]['uniqeid'])) {
            $result['uniqeid'] = $data[0]['uniqeid'];
        }

        if (!empty($data[0]['payment_data'])) {
            $result['payment_data'] = json_decode(
                Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['payment_data']),
                true
            );
        }

        // get the birth date if it is available in the quote.
        if (!empty($result['payment_data'])
            && !isset($result['payment_data']['NAME.BIRTHDATE'])
            && !empty($this->getQuote()->getCustomerDob())
        ) {
            $result['payment_data']['NAME.BIRTHDATE'] = $this->getQuote()->getCustomerDob();
        }

        return $result;
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
        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        $data = array(
            'PRESENTATION.AMOUNT' => $amount ?: $paymentHelper->format($order->getGrandTotal()),
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
     * Returns a basket array used for heidelpay invoicing.
     * This has to be overridden by the payment method.
     *
     * @param $order
     *
     * @return array
     */
    protected function getBasket($order)
    {
        return array();
    }

    /**
     * Getter for the payment method backend title
     *
     * @return string payment method title
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getAdminTitle()
    {
        return $this->getTitle();
    }

    /**
     * Getter for the payment method frontend title
     *
     * @return string payment method title
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getTitle()
    {
        $storeId = $this->getStoreId();
        $path = 'payment/' . $this->getCode() . '/';
        return $this->_getHelper()->__(Mage::getStoreConfig($path . 'title', $storeId));
    }

    /**
     * Api call to capture a given amount on an invoice
     *
     * @param Mage_Sales_Model_Order_Payment|Varien_Object $payment current payment object
     * @param $amount float amount to capture
     *
     * @return $this
     *
     * @throws \Mage_Core_Exception
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

            $config = $this->getMainConfig($this->getCode(), $authorisation['CRITERION_STOREID']);
            $config['PAYMENT.TYPE'] = 'CP';


            $frontend = $this->getFrontend($order->getRealOrderId(), $authorisation['CRITERION_STOREID']);
            $frontend['FRONTEND.MODE'] = 'DEFAULT';
            $frontend['FRONTEND.ENABLED'] = 'false';

            $user = $this->getUser($order, true);

            // todo: flag instead of code
            $basketDetails = $this->getCode() === 'hcdbs'; // If billsafe set to fin
            $basketData = $this->getBasketData($order, $basketDetails, $amount);

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


            $this->log('doRequest url : ' . $config['URL']);
            $this->log('doRequest params : ' . json_encode($params));

            $src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);

            $this->log('doRequest response : ' . json_encode($src));



            if ($src['PROCESSING_RESULT'] === 'NOK') {
                Mage::throwException('Heidelpay Error: ' . $src['PROCESSING_RETURN']);
                return $this;
            }

            $payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
            Mage::getModel('hcd/transaction')->saveTransactionData($src);
        }

        return $this;
    }

    /**
     *  Calculate whether a order can be captured or not
     *
     * @return bool
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function canCapture()
    {
        if (!$this->_canCapture) {
            return false;
        }

        // prevent frontend to capture an amount in case of direct booking with auto invoice
        if ($this->getStoreId() !== '0') {
            $this->log('try to capture amount in frontend ... this is not necessary !');
            return false;
        }

        // loading order object to check whether this
        $orderIncrementId = Mage::app()->getRequest()->getParam('order_id');
        $this->log('$orderIncrementId ' . $orderIncrementId);

        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order');
        $order->loadByAttribute('entity_id', (int)$orderIncrementId);

        /** @var $transaction HeidelpayCD_Edition_Model_Transaction */
        $transaction = Mage::getModel('hcd/transaction');
        if ($transaction->getOneTransactionByMethode($order->getRealOrderId(), 'PA') === false) {
            $this->log('there is no preauthorization for the order ' . $order->getRealOrderId());
            return false;
        }

        return true;
    }

    /**
     * Api call for refunding a given invoice
     *
     * @param Mage_Sales_Model_Order_Payment|Varien_Object $payment current payment object
     * @param float                                        $amount  amount to refund
     *
     * @return $this
     *
     * @throws \Mage_Core_Exception
     * @throws \Mage_Core_Model_Store_Exception
     * @throws Zend_Http_Client_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        /** @var $order Mage_Sales_Model_Order */
        $order = $payment->getOrder();

        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        /**  @var $transaction HeidelpayCD_Edition_Model_Transaction */
        $transaction = Mage::getModel('hcd/transaction');
        $captureData = $transaction->loadLastTransactionDataByUniqeId((string)$payment->getRefundTransactionId());

        $config = $this->getMainConfig($this->getCode(), $captureData['CRITERION_STOREID']);
        $config['PAYMENT.TYPE'] = 'RF';
        $frontend = $this->getFrontend($order->getRealOrderId(), $captureData['CRITERION_STOREID']);
        $frontend['FRONTEND.MODE'] = 'DEFAULT';
        $frontend['FRONTEND.ENABLED'] = 'false';
        $user = $this->getUser($order, true);
        $basketData = $this->getBasketData($order, false, $paymentHelper->format($amount));
        $basketData['IDENTIFICATION.REFERENCEID'] = (string)$payment->getRefundTransactionId();
        $params = $paymentHelper->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion = array()
        );
        $this->log('Refund url : ' . $config['URL']);
        $this->log('Refund params : ' . json_encode($params));

        $src = $paymentHelper->doRequest($config['URL'], $params);
        $this->log('Refund response : ' . json_encode($src));
        if ($src['PROCESSING_RESULT'] === 'NOK') {
            Mage::throwException('Heidelpay Error: ' . $src['PROCESSING_RETURN']);
            return $this;
        }

        $payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
        Mage::getModel('hcd/transaction')->saveTransactionData($src);
        return $this;
    }

    /**
     * Api call to issue a reversal on a given invoice
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return bool
     *
     * @throws \Mage_Core_Exception
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function reversal(Mage_Sales_Model_Order_Invoice $invoice, Mage_Sales_Model_Order_Payment $payment)
    {
        /** @var $order Mage_Sales_Model_Order */
        $order = $invoice->getOrder();
        $lastTransId = (string)$payment->getLastTransId();

        // build parameters
        $config = $this->getMainConfig($this->getCode(), $order->getStoreId());

        // set payment type to reversal
        $config['PAYMENT.TYPE'] = 'RV';

        $frontend = $this->getFrontend($order->getRealOrderId(), $order->getStoreId());
        $frontend['FRONTEND.MODE'] = 'DEFAULT';
        $frontend['FRONTEND.ENABLED'] = 'false';

        $user = $this->getUser($order, true);

        $basketData = $this->getBasketData($order); // gets presentation amount, currency and transactionId (order id)
        $basketData['IDENTIFICATION.REFERENCEID'] = $lastTransId;
        $params = Mage::helper('hcd/payment')->preparePostData(
            $config, $frontend, $user, $basketData,
            $criterion = array()
        );

        $this->log('Reversal url : ' . $config['URL']);
        $this->log('Reversal params : ' . json_encode($params));

        // send request
        $src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
        $this->log('Reversal response : ' . json_encode($src));
        if ($src['PROCESSING_RESULT'] === 'NOK') {
            $this->log(
                sprintf(
                    'Reversal transaction failed. Message: [%s], Code: [%s]',
                    $src['PROCESSING_RETURN'],
                    $src['PROCESSING_RETURN_CODE']
                ),
                'ERROR'
            );
            return false;
        }

        $payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
        Mage::getModel('hcd/transaction')->saveTransactionData($src);
        return true;
    }

    /**
     * Getter for customer given plus family name
     *
     * @param bool|Mage_Checkout_Model_Session $session checkout session
     *
     * @return string given plus family name
     */
    public function getCustomerName($session = false)
    {
        if ($session === true) {
            $session = $this->getCheckout();

            $billingAddress = $session->getQuote()->getBillingAddress();
        } else {
            $billingAddress = $this->getQuote()->getBillingAddress();
        }


        return $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
    }

    /**
     * Save additional payment data of the customer to the database
     *
     * @param $data array additional payment information of the customer
     * @param null $uniqueId
     *
     * @internal param string $uniqueID payment reference of a account registration
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function saveCustomerData($data, $uniqueId = null)
    {
        /** @var $customerData HeidelpayCD_Edition_Model_Customer */
        $customerData = Mage::getModel('hcd/customer');

        if (!empty($this->getCustomerData())) {
            $lastData = $this->getCustomerData();
            $customerData->load($lastData['id']);
        }

        $storeId = $this->getStoreId();
        $this->log('StoreID :' . $storeId);
        $customerId = $this->getQuote()->getBillingAddress()->getCustomerId();
        if (empty($customerId)) {
            $visitorData = Mage::getSingleton('core/session')->getVisitorData();
            $customerId = $visitorData['visitor_id'];
            $storeId = 0;
        }

        $customerData->setPaymentmethode($this->getCode());
        $customerData->setUniqeid($uniqueId);
        $customerData->setCustomerid($customerId);
        $customerData->setStoreid($storeId);
        $data['SHIPPING_HASH'] = $this->getShippingHash();
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
     *
     * @throws \Mage_Core_Exception
     * @throws Exception
     */
    public function chargeBackTransaction($order, $message = '')
    {
        // message block for direct debit charge back
        if ($order->hasInvoices()) {

            /** @var Mage_Sales_Model_Resource_Order_Invoice_Collection $invoices */
            $invoices = $order->getInvoiceCollection();

            /** @var  $invoice Mage_Sales_Model_Order_Invoice */
            foreach ($invoices as $invoice) {
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
            $order->getPayment()->getMethodInstance()->getStatusPending(false),
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
     *
     * @throws \Mage_Core_Exception
     */
    public function canceledTransaction($order, $message)
    {
        if ($order->canCancel()) {
            $order->cancel();

            $methodInstance = $order->getPayment()->getMethodInstance();
            $order->setState(
                $methodInstance->getStatusError(false),
                $methodInstance->getStatusError(true),
                $message
            );
        }

        return $order;
    }

    /**
     * Handle transaction with means processing
     *
     * @param Mage_Sales_Model_Order                $order
     * @param HeidelpayCD_Edition_Model_Transaction $data
     * @param string                                $message order history message
     *
     * @return Mage_Sales_Model_Order
     *
     * @throws \Mage_Core_Exception
     * @throws Exception
     */
    public function processingTransaction($order, $data, $message='')
    {
        /** @var HeidelpayCD_Edition_Helper_Payment $paymentHelper */
        $paymentHelper = Mage::helper('hcd/payment');

        $message = ($message === '') ? 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] : $message;
        $totallyPaid = false;

        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(true);

        /** @var HeidelpayCD_Edition_Model_Payment_Abstract $paymentMethodInstance */
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();

        if ($order->getOrderCurrencyCode() === $data['PRESENTATION_CURRENCY'] &&
            $paymentHelper->format($order->getGrandTotal()) === $data['PRESENTATION_AMOUNT']
        ) {
            $order->setState(
                $paymentMethodInstance->getStatusSuccess(),
                $paymentMethodInstance->getStatusSuccess(true),
                $message
            );
            $totallyPaid = true;
        } else {
            /*
             * in case rc is ack and amount is to low or currency miss match
             */
            $order->setState(
                $paymentMethodInstance->getStatusPartlyPaid(),
                $paymentMethodInstance->getStatusPartlyPaid(true),
                $message
            );
        }

        $code = $this->getCode();

        if ($totallyPaid
            && $order->canInvoice()
            && ($this->isSendingInvoiceAutomatically($data) || $code === 'hcdbs') // todo: flag instead of code
        ) {
            $invoice = $order->prepareInvoice();
            $invoice->register()->capture();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
            $invoice->setIsPaid(true);

            $order->setState(
                $paymentMethodInstance->getStatusSuccess(),
                $paymentMethodInstance->getStatusSuccess(true),
                __('Automatically invoiced by Heidelpay.')
            );
            $invoice->save();

            if ($this->canInvoiceOrderEmail()) {
                $invoiceMailComment = '';
                // todo: flag instead of code
                if ($code !== 'hcdiv') {
                    $info = $paymentMethodInstance->showPaymentInfo($data);
                    $invoiceMailComment = ($info === false) ? '' : '<h3>'
                        . $this->_getHelper()->__('Payment Information') . '</h3>' . $info . '<br/>';
                }

                $this->log('Sending invoice email for order #' . $order->getRealOrderId() . '...');
                $invoice->sendEmail(true, $invoiceMailComment);
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
     *
     * @throws \Mage_Core_Exception
     */
    public function pendingTransaction($order, $data, $message='')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] .' '.$message;

        $payment = $order->getPayment();
        $payment->setTransactionId($data['IDENTIFICATION_UNIQUEID']);

        $payment->setIsTransactionClosed(0);

        /** @noinspection PhpParamsInspection */
        $payment->setTransactionAdditionalInfo(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            null
        );

        $this->log('Setting status/state for order # ' . $order->getRealOrderId() . 'to pending.');
        $methodInstance = $payment->getMethodInstance();
        $order->setState(
            $methodInstance->getStatusPending(false),
            $methodInstance->getStatusPending(true),
            $message
        );
        $payment->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            true,
            $message
        );
        return $order;
    }

    /**
     * Returns if a shipment needs to be reported to heidelpay (Finalize request)
     *
     * @return bool
     */
    public function reportsShippingToHeidelpay()
    {
        return $this->_reportsShippingToHeidelpay;
    }

    /**
     * @return bool
     */
    public function isSendingInvoiceMailComment()
    {
        return $this->_sendsInvoiceMailComment;
    }

    /**
     * Returns true if the payment method is configured to automatically send an invoice email.
     *
     * @param $data
     *
     * @return bool
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function isSendingInvoiceAutomatically($data)
    {
        $path = 'payment/' . $this->getCode() . '/invioce';
        $autoInvoice = Mage::getStoreConfig($path, $data['CRITERION_STOREID']) === '1';
        $this->log('Auto invoiced: ' . ($autoInvoice ? 'enabled' : 'disabled'));
        return $autoInvoice;
    }

    /**
     * Returns the id of the current store.
     *
     * @return int
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    private function getStoreId()
    {
        return Mage::app()->getStore()->getId();
    }
}
