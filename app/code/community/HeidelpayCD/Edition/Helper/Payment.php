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
class HeidelpayCD_Edition_Helper_Payment extends HeidelpayCD_Edition_Helper_AbstractHelper
{
    /**
     * send request to heidelpay apo
     *
     * @param $url string url for the heidelpay api
     * @param array $params post parameter
     *
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
        ksort($result);
        return $result;
    }

    // @codingStandardsIgnoreLine more than 120 characters
    public function preparePostData($config = array(), $front = array(), $customer = array(), $basket = array(), $criterion = array())
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

        // sort the parameter
        ksort($params);

        return $params;
    }

    // @codingStandardsIgnoreLine should be refactored - issue #3
    protected function _setPaymentMethod($config = array(), $customer = array())
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
            /* invoice */
            case 'iv':
                $params['PAYMENT.CODE'] = "IV." . $type;
                break;
            /* invoice secured */
            case 'ivsec':
                $params['PAYMENT.CODE'] = "IV." . $type;
                break;
            /* Payolution invoice */
            case 'ivpol':
                $params['PAYMENT.CODE'] = 'IV.' . ($type !== 'RG' ? $type : 'PA') ;
                $params['ACCOUNT.BRAND'] = 'PAYOLUTION_DIRECT';
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
            // Santander Invoice
            case 'ivsan':
                $params['PAYMENT.CODE'] = 'IV.' . (($type === 'RG') ? 'PA' : $type);
                $params['ACCOUNT.BRAND'] = 'SANTANDER';
                break;

            default:
                $params['PAYMENT.CODE'] = strtoupper($config['PAYMENT.METHOD']) . '.' . $type;
                break;
        }

        return $params;
    }

    /**
     * get language code
     *
     * @param string $default default language code
     *
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
     * Returns the customer's/guest's order count
     *
     * @param int|null $customerId
     * @param bool $isGuest
     * @param string $emailAddress
     *
     * @return int
     */
    public function getCustomerOrderCount($customerId, $isGuest, $emailAddress)
    {
        // guest = get the order count by e-mail address and customer_id = null
        if ($isGuest || $customerId === null) {
            /** @var Mage_Eav_Model_Entity_Collection_Abstract $orders */
            $orders = Mage::getModel('sales/order')
                ->getCollection()
                ->addFieldToFilter('customer_id', null)
                ->addFieldToFilter('customer_email', $emailAddress)
                ->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_COMPLETE);

            return $orders->count();
        }

        /** @var Mage_Eav_Model_Entity_Collection_Abstract $orders */
        $orders = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_COMPLETE);

        return $orders->count();
    }

    /**
     * @param int|null $customerId
     * @param bool $isGuest
     *
     * @return string
     */
    public function getCustomerRegistrationDate($customerId, $isGuest)
    {
        /** @var Mage_Core_Model_Date $dateHelper */
        $dateHelper = Mage::getSingleton('core/date');

        // if the customer is a guest, return today's date.
        if ($isGuest || $customerId === null) {
            return $dateHelper->date('Y-m-d');
        }

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if ($customer->getCreatedAtTimestamp() !== null) {
            return $dateHelper->date('Y-m-d', $customer->getCreatedAtTimestamp());
        }

        // fallback, if getCreatedAtTimestamp somehow returns null.
        return $dateHelper->date('Y-m-d');
    }

    /**
     * helper to generate customer payment error messages
     *
     * @param mixed      $errorMsg
     * @param null|mixed $errorCode
     * @param null|mixed $orderNumber
     *
     * @return string
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
     * Get region code
     *
     * @param $countryCode string country code
     * @param $stateByName string state name
     *
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
