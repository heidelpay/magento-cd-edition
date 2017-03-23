<?php
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdsu extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdsu';
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * @inheritdoc
     */
    public function chargeBack($order, $message = "")
    {
        $message = Mage::helper('hcd')->__('chargeback');
        return parent::chargeBack($order, $message);
    }
}
