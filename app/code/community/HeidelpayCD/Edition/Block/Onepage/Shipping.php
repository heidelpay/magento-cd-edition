<?php
/**
 * Onepage shipping block
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
class HeidelpayCD_Edition_Block_Onepage_Shipping extends Mage_Checkout_Block_Onepage_Shipping
{
    public function getAddress()
    {
        if (!empty(Mage::getSingleton('checkout/session')->getHcdWallet())) {
            $wallet = Mage::getSingleton('checkout/session')->getHcdWallet();
            $this->_address = Mage::getModel('sales/quote_address')->setAddressType(
                Mage_Sales_Model_Quote_Address::TYPE_BILLING
            )
            ->setStoreId(Mage::app()->getStore()->getId())
            ->setFirstname($wallet['adress']['firstname'])
            ->setLastname($wallet['adress']['lastname'])
            ->setEmail($wallet['adress']['email'])
            ->setSuffix((''))
            ->setCompany('')
            ->setStreet(
                array(
                '0' => $wallet['adress']['street'][0],
                '1' => $wallet['adress']['street'][1]
                )
            )
            ->setCity($wallet['adress']['city'])
            ->setPostcode($wallet['adress']['postcode'])
            ->setCountry_id($wallet['adress']['country_id'])
            ->setRegion($wallet['adress']['region'])
            ->setRegion_id((string)$wallet['adress']['region_id'])
            ->setTelephone($wallet['adress']['telephone'])
            ->setFax();
            
            return $this->_address;
        } else {
            return parent::getAddress();
        }
    }
}
