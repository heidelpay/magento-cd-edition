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
class HeidelpayCD_Edition_Model_Payment_Hcdsu extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    **/
    protected $_code = 'hcdsu';
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
}
