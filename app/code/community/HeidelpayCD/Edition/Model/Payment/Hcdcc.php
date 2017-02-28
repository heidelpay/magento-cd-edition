<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method credit card
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH.
 * All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento2
 *
 * @author Jens Richter
 *
 * @package heidelpay
 * @subpackage magento
 * @category magento
 *
 */
class HeidelpayCD_Edition_Model_Payment_Hcdcc
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{

  
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    **/
    protected $_code = 'hcdcc';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    
    public function isRecognation()
    {
        $path = "payment/".$this->_code."/";
        $storeId =  Mage::app()->getStore()->getId();
        return Mage::getStoreConfig($path.'recognition', $storeId);
    }

    public function activeRedirect()
    {
        $recognation = $this->isRecognation();
        if ($recognation > 0) {
            return true;
        }

        return false;
    }
 
    protected $_formBlockType = 'hcd/form_creditcard';
    
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
}
