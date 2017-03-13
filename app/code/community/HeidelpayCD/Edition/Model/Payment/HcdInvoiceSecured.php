<?php
/**
 * Invoice secured payment method
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
class HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdInvoiceSecured';

    protected $_infoBlockType = 'hcd/info_invoice';
}
