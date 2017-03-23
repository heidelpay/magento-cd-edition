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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Helper_Payment extends Mage_Core_Helper_Abstract
{

    /**
     * send request to heidelpay apo
     *
     * @param $url string url for the heidelpay api
     * @param array $params post parameter
     * @return mixed|null|Zend_Http_Response response from heidelpay api
     */

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
        // @codingStandardsIgnoreLine parse_str is discouraged
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
     * real log function which will be called from all controllers and models
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
    // @codingStandardsIgnoreLine more than 120 characters
    public function preparePostData($config = array(),$front = array(),$customer = array(),$basket = array(),$criterion = array())
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


        $params = array_merge($params, $this->_setPaymentMethod($config, $customer));


        /* debit on registration */
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

        /* override FRONTEND.ENABLED if necessary */
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

    // @codingStandardsIgnoreLine should be refactored - issue #3
    protected function  _setPaymentMethod($config = array(), $customer = array())
    {
        $type = (!array_key_exists('PAYMENT.TYPE', $config)) ? 'PA' : $config['PAYMENT.TYPE'];
        /* Set payment method */
        switch ($config['PAYMENT.METHOD']) {
            /* sofort */
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
            /* postfinance */
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

                // masterpass as a payment method
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

    /**
     * function to split paymentCode into code and method
     * @param $paymentCode string payment code from response
     * @return array payment code and method as an array
     */
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

    /**
     * get language code
     * @param string $default default language code
     * @return string return current lang code
     */
    public function getLang($default = 'en')
    {
        $locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
        if (!empty($locale)) {
            return strtoupper($locale[0]);
        }

        return strtoupper($default);
    }

    /**
     * helper to generate customer payment error messages
     *
     * @param mixed $errorMsg
     * @param null|mixed $errorCode
     * @param null|mixed $orderNumber
     */
    public function handleError($errorMsg, $errorCode = null, $orderNumber = null)
    {
        // default is return generic error message
        if ($orderNumber != null) {
            $this->log('Ordernumber ' . $orderNumber . ' -> ' . $errorMsg . ' [' . $errorCode . ']', 'NOTICE');
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

    /**
     * get helper instance
     *
     * @return Mage_Core_Helper_Abstract
     */
    protected function _getHelper()
    {
        return Mage::helper('hcd');
    }

    /**
     * @param $quote Mage_Sales_Model_Quote quote object
     * @param $storeId int current store id
     * @param bool $includingShipment include
     * @return array return basket api array
     */
    public function basketItems($quote, $storeId, $includingShipment = false)
    {
        $shoppingCartItems = $quote->getAllVisibleItems();

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

        if ($includingShipment) {
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

    /**
     * Get region code
     *
     * @param $countryCode string country code
     * @param $stateByName string state name
     * @return mixed regionId
     */
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
