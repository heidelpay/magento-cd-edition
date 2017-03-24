<?php
/**
 * Masterpass payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdmpa extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    **/
    protected $_code = 'hcdmpa';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canBasketApi = true;
    
    protected $_formBlockType = 'hcd/form_masterpass';
    protected $_infoBlockType = 'hcd/info_masterpass';
    
    /**
    public function isAvailable($quote=null) {
        return true;
    }
    * @param mixed $code
    * @param mixed $customerId
    * @param mixed $storeId
    */
    public function getPaymentData()
    {
        $session = Mage::getSingleton('checkout/session');
        
        if ($session->getHcdWallet() !== false) {
            $hpdata = $session->getHcdWallet();
        
            if ($hpdata['code'] != $this->_code) {
                return '';
            }
            
            $html = (array_key_exists('mail', $hpdata)) ? $hpdata['mail'].'<br />' : '';
            $html .= (array_key_exists('brand', $hpdata)) ? $this->_getHelper()->__($hpdata['brand']).' ' : '';
            $html .= (array_key_exists('number', $hpdata)) ? $hpdata['number'].'<br/>' : '';
            $html .= (array_key_exists('expiryMonth', $hpdata))
                ? $this->_getHelper()->__('Expires').' '.$hpdata['expiryMonth'].'/' : '';
            $html .= (array_key_exists('expiryYear', $hpdata)) ? $hpdata['expiryYear'] : '';
            
            return $html;
        }
    }
    
    /*
     * MasterPass need shipping adress instead of  billing (REV20150707	FullCheckout Adressen)
     */
    public function getUser($order, $isReg=false)
    {
        $user = array();
        
        $user = parent::getUser($order, $isReg);
        $adress    = ($order->getShippingAddress() == false)
            ? $order->getBillingAddress()  : $order->getShippingAddress();
        $email = ($adress->getEmail()) ? $adress->getEmail() : $order->getCustomerEmail();
        
        
        $user['IDENTIFICATION.SHOPPERID']    = $adress->getCustomerId();
        if ($adress->getCompany() == true) {
            $user['NAME.COMPANY']    = trim($adress->getCompany());
        }

        $user['NAME.GIVEN']            = trim($adress->getFirstname());
        $user['NAME.FAMILY']        = trim($adress->getLastname());
        $user['ADDRESS.STREET']        = $adress->getStreet1()." ".$adress->getStreet2();
        $user['ADDRESS.ZIP']        = $adress->getPostcode();
        $user['ADDRESS.CITY']        = $adress->getCity();
        $user['ADDRESS.COUNTRY']    = $adress->getCountry();
        $user['CONTACT.EMAIL']        = $email;
        
        return $user;
    }
    
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
    
        $html = '<center><button type="button" title="MasterPass"
					class="btn-hcdmpa-payment-data" style="position: static"
					onclick="window.open(\'https://www.mastercard.com/mc_us/wallet/learnmore/'.$urlLang.'\')">
	</button>
    	<div sytle="margin-top: 10px  !important;">';
        
        $html .= (array_key_exists('CONTACT_EMAIL', $paymentData))
            ? $paymentData['CONTACT_EMAIL'].'<br />' : '';
        $html .= (array_key_exists('ACCOUNT_BRAND', $paymentData))
            ? $this->_getHelper()->__($paymentData['ACCOUNT_BRAND']).' ' : '';
        $html .= (array_key_exists('ACCOUNT_NUMBER', $paymentData))
            ? $paymentData['ACCOUNT_NUMBER'].'<br/>' : '';
        $html .= (array_key_exists('ACCOUNT_EXPIRY_MONTH', $paymentData))
            ? $this->_getHelper()->__('Expires').' '.$paymentData['ACCOUNT_EXPIRY_MONTH'].'/' : '';
        $html .= (array_key_exists('ACCOUNT_EXPIRY_YEAR', $paymentData))
            ? $paymentData['ACCOUNT_EXPIRY_YEAR'] : '';
        
        $html .= '</div></center>';
        
        $this->getCheckout()->setHcdPaymentInfo($html);
    }

    /**
     * @inheritdoc
     */
    public function chargeBack($order, $message = "")
    {
        $message = Mage::helper('hcd')->__('chargeback');
        return parent::chargeBack($order, $message);
    }
}
