<?php
/**
 * Abstract block
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
class HeidelpayCD_Edition_Block_Abstract extends Mage_Core_Block_Template
{
    public function getSession()
    {
        return Mage::getSingleton('core/session');
    }
    
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    public function getCurrentOrder()
    {
        $order = Mage::getModel('sales/order');
        $session = $this->getCheckout();
        $order->loadByIncrementId($session->getLastRealOrderId());
        
        return $order;
    }
}
