<?php

/**
 * Heidelpay abstract helper
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
class HeidelpayCD_Edition_Helper_AbstractHelper extends Mage_Core_Helper_Abstract
{
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

    /**
     * function to split paymentCode into code and method
     *
     * @param $paymentCode string payment code from response
     *
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
}
