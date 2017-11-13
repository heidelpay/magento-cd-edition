<?php /** @noinspection LongInheritanceChainInspection */

/**
 * PayPal payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcdpal extends HeidelpayCD_Edition_Model_Payment_Abstract
{

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdpal constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdpal';
        $this->_canCapture = true;
        $this->_canCapturePartial = true;
    }

    /**
     * Customer parameter for heidelpay api call
     * PayPal seller protection, need shipping address instead of billing (PAYPAL REV 20141215).
     *
     * @param $order Mage_Sales_Model_Order magento order object
     * @param bool $isReg in case of registration
     *
     * @return array
     *
     */
    public function getUser($order, $isReg=false)
    {
        $user = parent::getUser($order, $isReg);
        $address    = ($order->getShippingAddress() === false)
            ? $order->getBillingAddress()  : $order->getShippingAddress();
        $email = $address->getEmail() ?: $order->getCustomerEmail();
        
        
        $user['IDENTIFICATION.SHOPPERID']   = $address->getCustomerId();
        if ($address->getCompany() === true) {
            $user['NAME.COMPANY']           = trim($address->getCompany());
        }

        $user['NAME.GIVEN']                 = trim($address->getFirstname());
        $user['NAME.FAMILY']                = trim($address->getLastname());
        $user['ADDRESS.STREET']             = $address->getStreet1() . ' ' . $address->getStreet2();
        $user['ADDRESS.ZIP']                = $address->getPostcode();
        $user['ADDRESS.CITY']               = $address->getCity();
        $user['ADDRESS.COUNTRY']            = $address->getCountry();
        $user['CONTACT.EMAIL']              = $email;
        
        return $user;
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
