<?php

class HeidelpayCD_Edition_Model_Transaction extends Mage_Core_Model_Abstract
{
	    public function _construct()
        {
            $this->_init('hcd/transaction');
            parent::_construct();
        }
       
       public function saveTransactionData($data, $source='shop') {
       	
       	$PaymentCode = Mage::helper('hcd/payment')->splitPaymentCode($data['PAYMENT_CODE']);
       	
       	$this->setPaymentMethode($PaymentCode[0]);
		$this->setPaymentType($PaymentCode[1]);
		$this->setTransactionid($data['IDENTIFICATION_TRANSACTIONID']);
		$this->setUniqeid($data['IDENTIFICATION_UNIQUEID']);
		$this->setResult($data['PROCESSING_RESULT']);
		$this->setShortid($data['IDENTIFICATION_SHORTID']);
		$this->setStatuscode($data['PROCESSING_STATUS_CODE']);
		$this->setReturn($data['PROCESSING_RETURN']);
		$this->setReturncode($data['PROCESSING_RETURN_CODE']);
		$this->setJsonresponse(Mage::getModel('hcd/resource_encryption')->encrypt(json_encode($data)));
		$this->setDatetime(date('Y-m-d H:i:s'));
		$this->setSource($source);
		return $this->save();
       	
       }
       
       public function loadTransactionDataByX($filter=array(),$sortby=false) {
       	
       		$data = array();
       		$trans = $this->getCollection();
       			
       			foreach($filter AS $k => $v)
				$trans->addFieldToFilter($k,$v);
				
				if ($sortby) {
					$trans->getSelect()->order($sortby);
				}
			
			$trans->load();
				
				
				
			$data = $trans->getData();
			$temp = array();
			foreach($data AS $k => $v) {
				$temp[] =  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[$k]['jsonresponse']),true);
			}
			    
			return $temp;
       }
       
       public function loadLastTransactionDataByTransactionnr($transid) {
       	
       		$data = array();
       		$trans = $this->getCollection();
       		$trans->addFieldToFilter('transactionid',$transid);
			$trans->getSelect()->order('id DESC');
			$trans->getSelect()->limit(1);	
			$trans->load();
				
				
			$data = $trans->getData();
			return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']),true);
			    
       }
       
        public function loadLastTransactionDataByUniqeId($id) {
       	
       		$data = array();
       		$trans = $this->getCollection();
       		$trans->addFieldToFilter('uniqeid',$id);
			$trans->getSelect()->limit(1);	
			$trans->load();
				
				
			$data = $trans->getData();
			if (isset($data[0])){ 
				return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']),true);
			} else {
			
				return false;
			}    
       }
       
       public function getOneTransactionByMethode($transid , $methode) {
       	
       		$data = false;
       		$trans = $this->getCollection();
       		$trans->addFieldToFilter('transactionid',$transid)
       			  ->addFieldToFilter('Payment_Type', $methode );;
			$trans->getSelect()->order('id DESC');
			$trans->getSelect()->limit(1);	
			$trans->load();
				
				
			$data = $trans->getData();
			
			if (isset($data[0])){ 
				return  json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['jsonresponse']),true);
			} else {
			
				return false;
			}
			    
       }
        
}
