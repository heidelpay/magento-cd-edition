<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Debit card payment method
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
class HeidelpayCD_Edition_Model_Payment_Hcddc extends HeidelpayCD_Edition_Model_Payment_Abstract
{

    /**
     * HeidelpayCD_Edition_Model_Payment_Hcddc constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcddc';
        $this->_canCapture = true;
        $this->_canCapturePartial = true;
        $this->_canReversal = true;
        $this->_formBlockType = 'hcd/form_debitcard';
    }

    /**
     * Returns the store configuration for recognition of this payment method.
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function isRecognition()
    {
        $path = 'payment/' . $this->getCode() . '/';
        $storeId =  Mage::app()->getStore()->getId();
        return Mage::getStoreConfig($path.'recognition', $storeId);
    }

    /**
     * @return bool payment method will redirect the customer directly to heidelpay
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function activeRedirect()
    {
        return $this->isRecognition() > 0;
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
