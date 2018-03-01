<?php

/**
 * Invoice secured payment method
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * payment code
     *
     * @var string payment code
     */
    protected $_code = 'hcdivsec';

    /**
     * @inheritdoc
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPOST('payment');
        return parent::validate();
    }
}
