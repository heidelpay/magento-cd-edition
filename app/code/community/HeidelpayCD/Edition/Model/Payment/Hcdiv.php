<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method invoice
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
class HeidelpayCD_Edition_Model_Payment_Hcdiv
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * @var string code of the current payment method
     */
    protected $_code = 'hcdiv';

    /**
     * @var string info block for checkout
     */
    protected $_infoBlockType = 'hcd/info_invoice';
}
