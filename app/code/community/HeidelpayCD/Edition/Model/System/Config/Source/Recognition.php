<?php
/**
 * Recognition configuration model
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
class HeidelpayCD_Edition_Model_System_Config_Source_Recognition
{
    /**
     * Returns the recognition configuration option for the backend
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value'=>'0', 'label'=>Mage::helper('hcd')->__('no recognition')),
            array('value'=>'1', 'label'=>Mage::helper('hcd')->__('only if shippping adress is unchanged')),
            array('value'=>'2', 'label'=>Mage::helper('hcd')->__('always'))
        );
    }
}
