<?php
/**
 * Invoice by Santander form Block class
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2017-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento-cd-edition
 *
 * @author Stephano Vogel
 *
 * @package heidelpay/magento-cd-edition/block/form/santander-invoice
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Block_Form_SantanderInvoice extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('hcd/form/santander-invoice.phtml');
    }
}
