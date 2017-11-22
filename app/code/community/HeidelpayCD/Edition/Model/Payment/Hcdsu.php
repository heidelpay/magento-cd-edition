<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Sofort payment method
 *
 * Also called "Sofortüberweisung" in germany.
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
class HeidelpayCD_Edition_Model_Payment_Hcdsu extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdsu constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdsu';
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
