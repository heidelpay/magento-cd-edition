<?php
/**
 * Direct debit info block
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
class HeidelpayCD_Edition_Block_Info_DirectDebit extends Mage_Payment_Block_Info
{
    public function toPdf()
    {
        $this->setTemplate('hcd/info/pdf/directdebit.phtml');
        return $this->toHtml();
    }
}
