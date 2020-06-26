<?php
/**
 * Heidelpay encryption model
 *
 * replace the magento md5 hashing with sha256
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
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Resource_Encryption extends Mage_Core_Model_Encryption
{
    public function getHash($string, $salt = false)
    {
        if ($salt === false) {
            $salt = (string)Mage::getConfig()->getNode('global/crypt/key');
        }

        return hash('sha256', $salt . $string);
    }
    
    public function validateHash($string, $hash)
    {
        return $this->getHash((string)$string) === $hash;
    }
}
