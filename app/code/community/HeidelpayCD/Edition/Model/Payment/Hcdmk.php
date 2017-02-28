<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method mangir cart
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
 * @deprecated Payment method mangir cart is not longer available
 *
 */
class HeidelpayCD_Edition_Model_Payment_Hcdmk
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdmk';
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    
    public function isAvailable($quote=null)
    {
        $currencyCode=$this->getQuote()->getQuoteCurrencyCode();
        if (!empty($currencyCode) && $currencyCode != 'TRY') {
            return false;
        }

        return parent::isAvailable($quote);
    }
}
