<?php /** @noinspection LongInheritanceChainInspection */

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
class HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdivsec';
        $this->_canReversal = true;
        $this->_sendsInvoiceMailComment = true;
        $this->_reportsShippingToHeidelpay = true;
        $this->_canBasketApi = true;
    }

    /**
     * @inheritdoc
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPost('payment');
        return parent::validate();
    }
}
