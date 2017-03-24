<?php
/**
 * Transaction model
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
class HeidelpayCD_Edition_Model_Transaction extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        $this->_init('hcd/transaction');
        parent::_construct();
    }
       
    public function saveTransactionData($data, $source='shop')
    {
        $paymentCode = Mage::helper('hcd/payment')->splitPaymentCode($data['PAYMENT_CODE']);
           
        $this->setPaymentMethode($paymentCode[0]);
        $this->setPaymentType($paymentCode[1]);
        $this->setTransactionid($data['IDENTIFICATION_TRANSACTIONID']);
        $this->setUniqeid($data['IDENTIFICATION_UNIQUEID']);
        $this->setResult($data['PROCESSING_RESULT']);
        $this->setShortid($data['IDENTIFICATION_SHORTID']);
        $this->setStatuscode($data['PROCESSING_STATUS_CODE']);
        $this->setReturn($data['PROCESSING_RETURN']);
        $this->setReturncode($data['PROCESSING_RETURN_CODE']);
        $this->setJsonresponse(Mage::getModel('hcd/resource_encryption')->encrypt(json_encode($data)));
        // @codingStandardsIgnoreLine should be refactored - issue #2
        $this->setDatetime(date('Y-m-d H:i:s'));
        $this->setSource($source);
        return $this->save();
    }
       
    public function loadTransactionDataByX($filter=array(), $sortby=false)
    {
        $data = array();
        $trans = $this->getCollection();
                   
        foreach ($filter as $k => $v) {
            $trans->addFieldToFilter($k, $v);
        }
                
        if ($sortby) {
            $trans->getSelect()->order($sortby);
        }
            
        $trans->load();
                
                
                
        $data = $trans->getData();
        $temp = array();
        foreach ($data as $k => $v) {
            $temp[] =  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[$k]['jsonresponse']), true);
        }
                
        return $temp;
    }
       
    public function loadLastTransactionDataByTransactionnr($transid)
    {
        $data = array();
        $trans = $this->getCollection();
        $trans->addFieldToFilter('transactionid', $transid);
        $trans->getSelect()->order('id DESC');
        // @codingStandardsIgnoreLine
        $trans->getSelect()->limit(1);
        $trans->load();
                
                
        $data = $trans->getData();

        // @codingStandardsIgnoreLine seem to be a bug in marketplace ready
        if (is_array($data)) {
            return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']), true);
        }

        return false;
    }
       
    public function loadLastTransactionDataByUniqeId($id)
    {
        $data = array();
        $trans = $this->getCollection();
        $trans->addFieldToFilter('uniqeid', $id);
        // @codingStandardsIgnoreLine should be refactored - issue #6
        $trans->getSelect()->limit(1);
        $trans->load();
                
                
        $data = $trans->getData();
        if (isset($data[0])) {
            return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']), true);
        } else {
            return false;
        }
    }
       
    public function getOneTransactionByMethode($transid, $methode)
    {
        $data = false;
        $trans = $this->getCollection();
        $trans->addFieldToFilter('transactionid', $transid)
                     ->addFieldToFilter('Payment_Type', $methode);
        $trans->getSelect()->order('id DESC');
        // @codingStandardsIgnoreLine should be refactored - issue #6
        $trans->getSelect()->limit(1);
        $trans->load();
                
                
        $data = $trans->getData();
            
        if (isset($data[0])) {
            return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']), true);
        } else {
            return false;
        }
    }
}
