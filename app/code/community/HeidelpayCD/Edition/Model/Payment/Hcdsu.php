<?php
namespace Heidelpay\Magento\Model\Payment;

/**
 * heidelpay payment method prepayment
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH.
 * All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento2
 *
 * @author Jens Richter
 *
 * @package heidelpay
 * @subpackage magento
 * @category magento
 *
 */
class HeidelpayCD_Edition_Model_Payment_Hcdsu
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdsu';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
}
