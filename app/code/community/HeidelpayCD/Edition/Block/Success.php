<?php
/**
 * Success block
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
class HeidelpayCD_Edition_Block_Success extends HeidelpayCD_Edition_Block_Abstract
{
    public function showPaymentInfo()
    {
        $return = array();
        $order = $this->getCurrentOrder();
        $session = $this->getCheckout();
        
        $info = ($session->getHcdPaymentInfo() !== false) ? $session->getHcdPaymentInfo() : false;
        
        if (!empty($info)) {
            $return['Title'] =    $order->getPayment()->getMethodInstance()->getTitle();
            $return['Message'] = $session->getHcdPaymentInfo();
            
            $session->unsHcdPaymentInfo();
            
            return $return;
        }
        
        return false;
    }
}
