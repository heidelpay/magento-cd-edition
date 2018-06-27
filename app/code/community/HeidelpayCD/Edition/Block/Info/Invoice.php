<?php /** @noinspection LongInheritanceChainInspection */

/**
 * Invoice info block
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
class HeidelpayCD_Edition_Block_Info_Invoice extends Mage_Payment_Block_Info
{
    /**
     * {@inheritDoc}
     */
    public function toPdf()
    {
        $this->setTemplate('hcd/info/pdf/invoice.phtml');
        return $this->toHtml();
    }
}
