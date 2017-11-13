<?php
/**
 * MangirKart payment method
 *
 * This payment method is deprecated and exists for backwards compatibility purposes only.
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
 *
 * @deprecated This payment method is not longer available
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdmk extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * HeidelpayCD_Edition_Model_Payment_Hcdmk constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_code = 'hcdmk';
        $this->_canRefund = false;
        $this->_canRefundInvoicePartial = false;
    }

    public function isAvailable($quote=null)
    {
        return false;
    }
}
