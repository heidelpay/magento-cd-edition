<?php
namespace Heidelpay\Magento\Model\Payment;
/**
 * heidelpay payment method giropay
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
class HeidelpayCD_Edition_Model_Payment_Hcdgp
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdgp';
    protected $_formBlockType = 'hcd/form_giropay';
    
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
    
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');
        
        if ($payment['method'] == $this->_code) {
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException(
                    $this->_getHelper()
                    ->__('Please specify a account holder')
                );
            }

            if (empty($payment[$this->_code.'_iban'])) {
                Mage::throwException(
                    $this->_getHelper()
                    ->__('Please specify a iban or account')
                );
            }

            if (empty($payment[$this->_code.'_bic'])) {
                Mage::throwException(
                    $this->_getHelper()->__('Please specify a bic or bank code')
                );
            }
        
            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
            
            if (preg_match('#^[\d]#', $payment[$this->_code.'_iban'])) {
                $params['ACCOUNT.NUMBER'] = $payment[$this->_code.'_iban'];
            } else {
                $params['ACCOUNT.IBAN'] = $payment[$this->_code.'_iban'];
            }
        
            if (preg_match('#^[\d]#', $payment[$this->_code.'_bic'])) {
                $params['ACCOUNT.BANK'] = $payment[$this->_code.'_bic'];
                $params['ACCOUNT.COUNTRY'] = $this->getQuote()
                    ->getBillingAddress()->getCountry();
            } else {
                $params['ACCOUNT.BIC'] = $payment[$this->_code.'_bic'];
            }
            
            
            $this->saveCustomerData($params);
            
            return $this;
        }
        
        return $this;
    }
}
