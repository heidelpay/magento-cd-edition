<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Masterpass payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdmpa extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdpp constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdmpa';
        $this->_canCapture = true;
        $this->_canCapturePartial = true;
        $this->_canReversal = true;
        $this->_canBasketApi = true;

        $this->_formBlockType = 'hcd/form_masterpass';
        $this->_infoBlockType = 'hcd/info_masterpass';
        $this->_showAdditionalPaymentInformation = true;
    }

    /**
     * @return string
     */
    public function getPaymentData()
    {
        $session = Mage::getSingleton('checkout/session');

        /** @noinspection PhpUndefinedMethodInspection */
        if ($session->getHcdWallet() !== false) {
            /** @noinspection PhpUndefinedMethodInspection */
            $hpdata = $session->getHcdWallet();
        
            if ($hpdata['code'] !== $this->getCode()) {
                return '';
            }
            
            $html = array_key_exists('mail', $hpdata) ? $hpdata['mail'].'<br />' : '';
            $html .= array_key_exists('brand', $hpdata) ? $this->_getHelper()->__($hpdata['brand']).' ' : '';
            $html .= array_key_exists('number', $hpdata) ? $hpdata['number'].'<br/>' : '';
            $html .= array_key_exists('expiryMonth', $hpdata)
                ? $this->_getHelper()->__('Expires').' '.$hpdata['expiryMonth'].'/' : '';
            $html .= array_key_exists('expiryYear', $hpdata) ? $hpdata['expiryYear'] : '';
            
            return $html;
        }

        return '';
    }
    
    /**
     * Customer parameter for heidelpay api call
     * MasterPass needs shipping address instead of  billing (REV20150707 FullCheckout addresses).
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $isReg in case of registration
     *
     * @return array
     *
     */
    public function getUser($order, $isReg = false)
    {
        $user = parent::getUser($order, $isReg);
        $address    = ($order->getShippingAddress() === false)
            ? $order->getBillingAddress()  : $order->getShippingAddress();
        $email = $address->getEmail() ?: $order->getCustomerEmail();
        
        
        $user['IDENTIFICATION.SHOPPERID']    = $address->getCustomerId();
        if ($address->getCompany() === true) {
            $user['NAME.COMPANY']    = trim($address->getCompany());
        }

        $user['NAME.GIVEN']          = trim($address->getFirstname());
        $user['NAME.FAMILY']         = trim($address->getLastname());
        $user['ADDRESS.STREET']      = $address->getStreet1() . ' ' . $address->getStreet2();
        $user['ADDRESS.ZIP']         = $address->getPostcode();
        $user['ADDRESS.CITY']        = $address->getCity();
        $user['ADDRESS.COUNTRY']     = $address->getCountry();
        $user['CONTACT.EMAIL']       = $email;
        
        return $user;
    }

    /**
     * Generates a customer message for the success page
     *
     * Will be used for prepayment and direct debit to show the customer
     * the billing information
     *
     * @param array $paymentData transaction details form heidelpay api
     *
     * @return bool| string  customer message for the success page
     */
    public function showPaymentInfo($paymentData)
    {
        $lang = Mage::app()->getLocale()->getLocaleCode();
        switch ($lang) {
        case 'de_DE':
        case 'de_AT':
        case 'de_CH':
                $urlLang = 'de/DE';
            break;
        case 'fr_FR':
                $urlLang = 'fr/FR';
            break;
        case 'en_GB':
        case 'en_US':
        default:
                $urlLang = 'en/US';
            break;
        }


        $html = '<!--suppress HtmlDeprecatedTag --><center><button type="button" title="MasterPass"
					class="btn-hcdmpa-payment-data" style="position: static"
					onclick="window.open(\'https://www.mastercard.com/mc_us/wallet/learnmore/'.$urlLang.'\')">
	</button>
    	<div style="margin-top: 10px  !important;">';
        
        $html .= array_key_exists('CONTACT_EMAIL', $paymentData) ? $paymentData['CONTACT_EMAIL'].'<br />' : '';
        $html .= array_key_exists('ACCOUNT_BRAND', $paymentData)
            ? $this->_getHelper()->__($paymentData['ACCOUNT_BRAND']).' ' : '';
        $html .= array_key_exists('ACCOUNT_NUMBER', $paymentData)
            ? $paymentData['ACCOUNT_NUMBER'].'<br/>' : '';
        $html .= array_key_exists('ACCOUNT_EXPIRY_MONTH', $paymentData)
            ? $this->_getHelper()->__('Expires').' '.$paymentData['ACCOUNT_EXPIRY_MONTH'].'/' : '';
        $html .= array_key_exists('ACCOUNT_EXPIRY_YEAR', $paymentData)
            ? $paymentData['ACCOUNT_EXPIRY_YEAR'] : '';
        
        $html .= '</div></center>';

        /** @noinspection PhpUndefinedMethodInspection */
        $this->getCheckout()->setHcdPaymentInfo($html);

        return '';
    }

    /**
     * Handle charge back notices from heidelpay payment
     *
     * @param $order Mage_Sales_Model_Order
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     */
    public function chargeBackTransaction($order, $message = '')
    {
        /** @noinspection SuspiciousAssignmentsInspection */
        $message = Mage::helper('hcd')->__('chargeback');
        return parent::chargeBackTransaction($order, $message);
    }
}
