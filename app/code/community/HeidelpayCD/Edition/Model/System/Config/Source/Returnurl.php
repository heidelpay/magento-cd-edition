<?php
/**
 * Return url configuration model
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
// @codingStandardsIgnoreLine
class HeidelpayCD_Edition_Model_System_Config_Source_Returnurl
{
    /**
     * Return the configuration option for the return urk in case of a payment error
     *
     * @return array currently you can choose between basket and checkout page
     */
    public function toOptionArray()
    {
        return array(
            array('value'=>'basket', 'label'=>Mage::helper('hcd')->__('Basket')),
            array('value'=>'onepage', 'label'=>Mage::helper('hcd')->__('Onepage Checkout')),
            );
    }
}
