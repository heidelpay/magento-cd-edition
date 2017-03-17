<?php

/**
 * Payment Helper
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
class HeidelpayCD_Edition_Helper_Payment extends Mage_Core_Helper_Abstract
{
    protected $_invoiceOrderEmail = true;
    protected $_debug = false;

    public function doRequest($url, $params = array())
    {
        $client = new Zend_Http_Client(trim($url), array());

        if (array_key_exists('raw', $params)) {
            $client->setRawData(json_encode($params['raw']), 'application/json');
        } else {
            $client->setParameterPost($params);
        }

        if (extension_loaded('curl')) {
            $adapter = new Zend_Http_Client_Adapter_Curl();
            $adapter->setCurlOption(CURLOPT_SSL_VERIFYPEER, true);
            $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, 2);
            $adapter->setCurlOption(CURLOPT_SSLVERSION, 6);
            $client->setAdapter($adapter);
        }

        $response = $client->request('POST');
        $res = $response->getBody();


        if ($response->isError()) {
            $this->log("Request fail. Http code : " . $response->getStatus() . ' Message : ' . $res, 'ERROR');
            $this->log("Request data : " . json_encode($params), 'ERROR');
            if (array_key_exists('raw', $params)) {
                return $response;
            }
        }

        if (array_key_exists('raw', $params)) {
            return json_decode($res, true);
        }

        $result = null;
        parse_str($res, $result);

        return $result;
    }

    /**
     * abstract log function because of backtrace
     *
     * @param mixed $message
     * @param mixed $level
     * @param mixed $file
     */
    public function log($message, $level = "DEBUG", $file = false)
    {
        $callers = debug_backtrace();
        return $this->realLog($callers[1]['function'] . ' ' . $message, $level, $file);
    }

    /**
     * real log function which will becalled from all controllers and models
     *
     * @param mixed $message
     * @param mixed $level
     * @param mixed $file
     */
    public function realLog($message, $level = "DEBUG", $file = false)
    {
        $storeId = Mage::app()->getStore()->getId();
        $path = "hcd/settings/";
        $config = array();


        switch ($level) {
            case "CRIT":
                $lev = Zend_Log::CRIT;
                break;
            case "ERR":
            case "ERROR":
                $lev = Zend_Log::ERR;
                break;
            case "WARN":
                $lev = Zend_Log::WARN;
                break;
            case "NOTICE":
                $lev = Zend_Log::NOTICE;
                break;
            case "INFO":
                $lev = Zend_Log::INFO;
                break;
            default:
                $lev = Zend_Log::DEBUG;
                if (Mage::getStoreConfig($path . "log", $storeId) == 0) {
                    return true;
                }
                break;
        }

        $file = ($file === false) ? "Heidelpay.log" : $file;

        Mage::log($message, $lev, $file);
        return true;
    }

    public function preparePostData($config=array(),$front=array(),$customer=array(),$basket=array(),$criterion=array())
    {
        $params = array();
        /*
         * configuration part of this function
         */
        $params['SECURITY.SENDER'] = $config['SECURITY.SENDER'];
        $params['USER.LOGIN'] = $config['USER.LOGIN'];
        $params['USER.PWD'] = $config['USER.PWD'];

        switch ($config['TRANSACTION.MODE']) {
            case 'INTEGRATOR_TEST':
                $params['TRANSACTION.MODE'] = 'INTEGRATOR_TEST';
                break;
            case 'CONNECTOR_TEST':
                $params['TRANSACTION.MODE'] = 'CONNECTOR_TEST';
                break;
            default:
                $params['TRANSACTION.MODE'] = 'LIVE';
        }

        $params['TRANSACTION.CHANNEL'] = $config['TRANSACTION.CHANNEL'];


        $params = array_merge($params, $this->_setPaymentMethod($config));


        /* Debit on registration */
        if (array_key_exists('ACCOUNT.REGISTRATION', $config)) {
            $params['ACCOUNT.REGISTRATION'] = $config['ACCOUNT.REGISTRATION'];
            $params['FRONTEND.ENABLED'] = "false";
        }

        if (array_key_exists('SHOP.TYPE', $config)) {
            $params['SHOP.TYPE'] = $config['SHOP.TYPE'];
        }

        if (array_key_exists('SHOPMODUL.VERSION', $config)) {
            $params['SHOPMODUL.VERSION'] = $config['SHOPMODUL.VERSION'];
        }

        /* frontend configuration */

        /* override FRONTEND.ENABLED if nessessary */
        if (array_key_exists('FRONTEND.ENABLED', $front)) {
            $params['FRONTEND.ENABLED'] = $front['FRONTEND.ENABLED'];
            unset($front['FRONTEND.ENABLED']);
        }

        if (array_key_exists('FRONTEND.MODE', $front)) {
            $params['FRONTEND.MODE'] = $front['FRONTEND.MODE'];
            unset($front['FRONTEND.MODE']);
        } else {
            $params['FRONTEND.MODE'] = "WHITELABEL";
            $params['TRANSACTION.RESPONSE'] = "SYNC";
            $params['FRONTEND.ENABLED'] = 'true';
        }


        $params = array_merge($params, $front);

        /* costumer data configuration */
        $params = array_merge($params, $customer);

        /* basket data configuration */
        $params = array_merge($params, $basket);

        /* criterion data configuration */
        $params = array_merge($params, $criterion);

        $params['REQUEST.VERSION'] = "1.0";

        return $params;
    }

    protected function _setPaymentMethod($config=array())
    {
        $type = (!array_key_exists('PAYMENT.TYPE', $config)) ? 'PA' : $config['PAYMENT.TYPE'];
        /* Set payment methode */
        switch ($config['PAYMENT.METHOD']) {
            /* sofortbanking */
            case 'su':
                $params['ACCOUNT.BRAND'] = "SOFORT";
                $params['PAYMENT.CODE'] = "OT." . $type;
                break;
            /* griopay */
            case 'gp':
                $params['ACCOUNT.BRAND'] = "GIROPAY";
                $params['PAYMENT.CODE'] = "OT." . $type;
                break;
            /* ideal */
            case 'ide':
                $params['ACCOUNT.BRAND'] = "IDEAL";
                $params['PAYMENT.CODE'] = "OT." . $type;
                break;
            /* eps */
            case 'eps':
                $params['ACCOUNT.BRAND'] = "EPS";
                $params['PAYMENT.CODE'] = "OT." . $type;
                break;
            /* postfinace */
            case 'pf':
                $params['PAYMENT.CODE'] = "OT." . $type;
                break;
            /* paypal */
            case 'pal':
                $type = (!array_key_exists('PAYMENT.TYPE', $config)) ? 'DB' : $config['PAYMENT.TYPE'];
                $params['PAYMENT.CODE'] = "VA." . $type;
                $params['ACCOUNT.BRAND'] = "PAYPAL";
                break;
            /* prepayment */
            case 'pp':
                $params['PAYMENT.CODE'] = "PP." . $type;
                break;
            /* invoce */
            case 'iv':
                $params['PAYMENT.CODE'] = "IV." . $type;
                break;
            /* invoice secured */
            case 'ivsec':
                $params['PAYMENT.CODE'] = "IV." . $type;
                break;
            /* direct debit secured */
            case 'ddsec':
                $type = (!array_key_exists('PAYMENT.TYPE', $config)) ? 'DB' : $config['PAYMENT.TYPE'];
                $params['PAYMENT.CODE'] = "DD." . $type;
                break;
            /* BillSafe */
            case 'bs':
                $params['PAYMENT.CODE'] = "IV." . $type;
                $params['ACCOUNT.BRAND'] = "BILLSAFE";
                $params['FRONTEND.ENABLED'] = "false";
                break;
            /* MasterPass */
            case 'mpa':
                $type = (!array_key_exists('PAYMENT.TYPE', $config)) ? 'DB' : $config['PAYMENT.TYPE'];

                // masterpass as a payment methode
                if (!array_key_exists('IDENTIFICATION.REFERENCEID', $customer) and ($type == 'DB' or $type == 'PA')) {
                    $params['WALLET.DIRECT_PAYMENT'] = "true";
                    $params['WALLET.DIRECT_PAYMENT_CODE'] = "WT." . $type;
                    $type = 'IN';
                }

                $params['PAYMENT.CODE'] = "WT." . $type;
                $params['ACCOUNT.BRAND'] = "MASTERPASS";
                break;
            default:
                $params['PAYMENT.CODE'] = strtoupper($config['PAYMENT.METHOD']) . '.' . $type;
                break;
        }

        return $params;
    }

    public function mapStatus($data, $order, $message = false)
    {
        $this->log('mapStatus' . json_encode($data));
        $paymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);
        $totalypaid = false;
        $invoiceMailComment = '';

        if (strtoupper($data['CRITERION_LANGUAGE']) == 'DE') {
            $locale = 'de_DE';
            Mage::app()->getLocale()->setLocaleCode($locale);
            Mage::getSingleton('core/translate')->setLocale($locale)->init('frontend', true);
        }

        // handle charge back notifications for cc, dc and dd
        if ($paymentCode[1] == 'CB') {
            $this->log('charge back');

            if ($paymentCode[0] == 'DD') {
                // message block for direct debit charge back
                $message = Mage::helper('hcd')->__('debit failed');
            } else {
                // message block for credit and debit cart charge back
                $message = Mage::helper('hcd')->__('chargeback');
            }

            if ($order->hasInvoices()) {
                $invIncrementIDs = array();
                foreach ($order->getInvoiceCollection() as $inv) {
                    $this->log('Invoice Number '.(string)$inv->getIncrementId());
                    $inv->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                    $inv->setIsPaid(false);
                    $inv->save();
                }

                $order->setIsInProcess(false);
            }

            $order->setState(
                $order->getPayment()->getMethodInstance()->getStatusPendig(false),
                true,
                $message 
            );


            Mage::dispatchEvent('heidelpay_after_map_status_chargeback', array('order' => $order));
            $this->log('Is this order protected ? ' . (string)$order->isStateProtected());
            $order->save();
            return;
        }


        $message = (!empty($message)) ? $message : $data['PROCESSING_RETURN'];

        $quoteID = ($order->getLastQuoteId() === false) ? $order->getQuoteId() : $order->getLastQuoteId(); // last_quote_id workaround for trusted shop buyerprotection

        /**
         * Do nothing if status is already successful
         */
        if ($order->getStatus() == $order->getPayment()->getMethodInstance()->getStatusSuccess()) {
            return;
        }

        /**
         * If an order has been canceled, cloesed or complete do not change order status
         */
        if ($order->getStatus() == Mage_Sales_Model_Order::STATE_CANCELED or
            $order->getStatus() == Mage_Sales_Model_Order::STATE_CLOSED or
            $order->getStatus() == Mage_Sales_Model_Order::STATE_COMPLETE
        ) {
            // you can use this event for example to get a notification when a canceled order has been paid
            Mage::dispatchEvent('heidelpay_map_status_cancel', array('order' => $order, 'data' => $data));
            return;
        }

        if ($data['PROCESSING_RESULT'] == 'NOK') {
            if ($order->canCancel()) {
                $order->cancel();

                $order->setState(
                    $order->getPayment()->getMethodInstance()->getStatusError(false),
                    $order->getPayment()->getMethodInstance()->getStatusError(true),
                    $message
                );
            }
        } elseif (($paymentCode[1] == 'CP' or
                $paymentCode[1] == 'DB' or
                $paymentCode[1] == 'FI' or
                $paymentCode[1] == 'RC')
            and ($data['PROCESSING_RESULT'] == 'ACK' and $data['PROCESSING_STATUS_CODE'] != 80)
        ) {
            $message = (isset($data['ACCOUNT_BRAND']) and $data['ACCOUNT_BRAND'] == 'BILLSAFE')
                ? 'BillSafe Id: ' . $data['CRITERION_BILLSAFE_REFERENCE']
                : 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'];

            if ($paymentCode[0] == "IV" or $paymentCode[0] == "PP") {
                $message = Mage::helper('hcd')->__('recived amount ')
                    . $data['PRESENTATION_AMOUNT'] . ' ' . $data['PRESENTATION_CURRENCY'] . ' ' . $message;
            }

            $order->getPayment()->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
                ->setParentTransactionId($order->getPayment()->getLastTransId());
            $order->getPayment()->setIsTransactionClosed(true);

            if ($this->format($order->getGrandTotal()) == $data['PRESENTATION_AMOUNT'] and
                $order->getOrderCurrencyCode() == $data['PRESENTATION_CURRENCY']
            ) {
                $order->setState(
                    $order->getPayment()->getMethodInstance()->getStatusSuccess(false),
                    $order->getPayment()->getMethodInstance()->getStatusSuccess(true),
                    $message
                );
                $totalypaid = true;
            } else {
                /*
                 * in case rc is ack and amount is to low or curreny missmatch
                 */
                $order->setState(
                    $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(false),
                    $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(true),
                    $message
                );
            }

            $this->log('$totalypaid ' . $totalypaid);

            $code = $order->getPayment()->getMethodInstance()->getCode();

            $path = "payment/" . $code . "/";

            $this->log(
                $path . ' Auto invoiced :' . Mage::getStoreConfig(
                    $path . "invioce",
                    $data['CRITERION_STOREID']
                ) . $data['CRITERION_STOREID']
            );

            if ($order->canInvoice() and (Mage::getStoreConfig(
                $path . "invioce",
                $data['CRITERION_STOREID']
            ) == 1 or $code == 'hcdbs') and $totalypaid === true
            ) {
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
                            . $this->__('Payment Information') . '</h3>' . $info . '<br/>';
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
        } else {
            if ($order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusSuccess() and
                $order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusError()
            ) {
                $message = (isset($data['ACCOUNT_BRAND']) and $data['ACCOUNT_BRAND'] == 'BILLSAFE')
                    ? 'BillSafe Id: ' . $data['CRITERION_BILLSAFE_REFERENCE']
                    : 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'];
                $order->getPayment()->setTransactionId($data['IDENTIFICATION_UNIQUEID']);
                $order->getPayment()->setIsTransactionClosed(0);
                $order->getPayment()->setTransactionAdditionalInfo(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                    null
                );

                // prepare invoice in case of payment method invoice

                if ($paymentCode[0] == "IV" and $paymentCode[1] == "PA" and $order->canInvoice()) {
                    $invoice = $order->prepareInvoice();
                    $invoice->register()->capture();
                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                    $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);
                    $invoice->setIsPaid(false);
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
                                . $this->__('Payment Information') . '</h3>' . $info . '<br/>';
                        }

                        $invoice->sendEmail(true, $invoiceMailComment); // send invoice mail
                    }


                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();
                }

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
            }
        }

        Mage::dispatchEvent('heidelpay_after_map_status', array('order' => $order));
        $order->save();
    }

    public function splitPaymentCode($paymentCode)
    {
        return preg_split('/\./', $paymentCode);
    }

    /**
     * function to format amount
     *
     * @param mixed $number
     */
    public function format($number)
    {
        return number_format($number, 2, '.', '');
    }

    public function getLang($default = 'en')
    {
        $locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
        if (!empty($locale)) {
            return strtoupper($locale[0]);
        }

        return strtoupper($default); //TOBO falses Module
    }

    /**
     * helper to generate customer payment error messages
     *
     * @param mixed $errorMsg
     * @param null|mixed $errorCode
     * @param null|mixed $ordernr
     */
    public function handleError($errorMsg, $errorCode = null, $ordernr = null)
    {
        // default is return generic error message
        if ($ordernr != null) {
            $this->log('Ordernumber ' . $ordernr . ' -> ' . $errorMsg . ' [' . $errorCode . ']', 'NOTICE');
        }

        if ($errorCode) {
            if (!preg_match(
                '/HPError-[0-9]{3}\.[0-9]{3}\.[0-9]{3}/', $this->_getHelper()->__('HPError-' . $errorCode),
                $matches
            )
            ) { //JUST return when snipet exists
                return $this->_getHelper()->__('HPError-' . $errorCode);
            }
        }

        return $this->_getHelper()->__('An unexpected error occurred. Please contact us to get further information.');
    }

    protected function _getHelper()
    {
        return Mage::helper('hcd');
    }

    public function basketItems($quote, $storeId, $withShipping = false)
    {
        $shoppingCartItems = $quote->getAllVisibleItems();

        $shoppingCart = array();


        $shoppingCart = array(

            'authentication' => array(

                'login' => trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
                'sender' => trim(Mage::getStoreConfig('hcd/settings/security_sender', $storeId)),
                'password' => trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),

            ),


            'basket' => array(
                'amountTotalNet' => floor(bcmul($quote->getGrandTotal(), 100, 10)),
                'currencyCode' => $quote->getGlobalCurrencyCode(),
                'amountTotalDiscount' => floor(bcmul($quote->getDiscountAmount(), 100, 10)),
                'itemCount' => count($shoppingCartItems)
            )


        );

        $count = 1;

        foreach ($shoppingCartItems as $item) {
            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $item->getItemId(),
                'quantity' => ($item->getQtyOrdered() !== false) ? floor($item->getQtyOrdered()) : $item->getQty(),
                'vat' => floor($item->getTaxPercent()),
                'amountVat' => floor(bcmul($item->getTaxAmount(), 100, 10)),
                'amountGross' => floor(bcmul($item->getRowTotalInclTax(), 100, 10)),
                'amountNet' => floor(bcmul($item->getRowTotal(), 100, 10)),
                'amountPerUnit' => floor(bcmul($item->getPrice(), 100, 10)),
                'amountDiscount' => floor(bcmul($item->getDiscountAmount(), 100, 10)),
                'type' => 'goods',
                'title' => $item->getName(),
                'imageUrl' => (string)Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail')
            );
            $count++;
        };

        if ($withShipping) {
            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $count,
                "type" => "shipment",
                "title" => "Shipping",
                'quantity' => 1,
                'vat' => '',
                'amountVat' => '',
                'amountGross' => '',
                'amountNet' => '',
                'amountPerUnit' => '',
                'amountDiscount' => ''
            );
        }


        return $shoppingCart;
    }

    public function getRegion($countryCode, $stateByName)
    {

        $regionData = Mage::getModel('directory/region')->getResourceCollection()
            ->addCountryFilter($countryCode)
            ->load();


        $regionId = null;

        foreach ($regionData as $region) {
            if (strtolower($stateByName) == strtolower($region['name']) or $stateByName == $region['code']) {
                return $region['region_id'];
            }
        }

        // Return last region if mapping fails
        return $region['region_id'];
    }
}
