<?php
class HeidelpayCD_Edition_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
	/*{{{Vars*/
	/**
	 * unique internal payment method identifier
	 *    
	 * @var string [a-z0-9_]   
	 */
	protected $_code = 'abstract';
	protected $_order;
	protected $_isGateway = true;
	protected $_canAuthorize = false;
	protected $_canCapture = false;
	protected $_canCapturePartial = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = false;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	var 	$_canBasketApi = false;
	protected $_isInitializeNeeded = true;
	
	
	protected $_live_url 	= 'https://heidelpay.hpcgw.net/ngw/post';
	protected $_sandbox_url = 'https://test-heidelpay.hpcgw.net/ngw/post';
	
	/**
	 * 
	 */
	public function	activRedirct() {
		return true ;
	} 
	
	/*
	public function	getCode() {
		return $this->_code ;
	} 
	*/
	
	protected $_formBlockType = 'hcd/form_desconly';
	
	public function getFormBlockType(){
		return $this->_formBlockType;
	}
	
	public function getCheckout()
		{
		return Mage::getSingleton('checkout/session');
		}
	/** Get Status Pending*/	
	public function getStatusPendig($param=false) {
		
		if ($param == false) return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; // status 
		
		return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; //state
		
	}
	/** Get Status Error/Cancel*/
	public function getStatusError($param=false) {
		
		if ($param == false) return Mage_Sales_Model_Order::STATE_CANCELED; // status 
		
		return Mage_Sales_Model_Order::STATE_CANCELED; //state
		
	}
	/** Get Status Success*/
	public function getStatusSuccess($param=false) {
		
		if ($param == false) return Mage_Sales_Model_Order::STATE_PROCESSING; // status 
		
		return Mage_Sales_Model_Order::STATE_PROCESSING; //state
	}
	/** Get Status PartlyPaid*/
	public function getStatusPartlyPaid($param=false) {
		
		if ($param == false) return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW; // status 
		
		return Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW; //state
		
	}
	
	/**
	 * Get current quote
	 *
	 * @return Mage_Sales_Model_Quote
	 */
	public function getQuote()
		{
		return $this->getCheckout()->getQuote();
		}
	
	/**
	 * Get heidelpay session namespace
	 *
	 * @return Mage_Heidelpay_Model_Session
	 */
	public function getSession()
		{
		return Mage::getSingleton('core/session');
		}
	
	public function validate()/*{{{*/
		{
		parent::validate();
		return $this;
		}/*}}}*/
	
	public function initialize($paymentAction, $stateObject)
		{
		/*
		 $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
		 $stateObject->setState($state);
		 $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
		 $stateObject->setIsNotified(false);
		 */
		}
	
	/**
	 * Retirve block type for display method information
	 *
	 * @return string
	 */
	public function getInfoBlockType()/*{{{*/
		{
		return $this->_infoBlockType;
		}/*}}}*/
	
	/**
	 * Return true if the method can be used at this time
	 *
	 * @return bool
	 */
	public function isAvailable($quote=null)
		{
		# Minimum and maximum amount
		$totals = $this->getQuote()->getTotals();
		if(!isset($totals['grand_total']) ) return false;
		$storeId =  Mage::app()->getStore()->getId();
		
		/*
		 if(Mage::getStoreConfig("hcd/settings/transactionmode", $storeId) != 0) 
		 Mage::getSingleton('core/session')->addNotice('"'.$this->getTitle().'"'.$this->_getHelper()->__(' is in sandbox mode.'));
		 */
		
		$amount = sprintf('%1.2f', $totals['grand_total']->getData('value'));
		$amount = $amount * 100;
		$path = "payment/".$this->_code."/";
		
		$minamount = Mage::getStoreConfig($path.'min_amount', $storeId );
		$maxamount = Mage::getStoreConfig($path.'max_amount', $storeId );
		if (is_numeric($minamount) && $minamount > 0 && $minamount > $amount) return false;
		if (is_numeric($maxamount) && $maxamount > 0 && $maxamount < $amount) return false;
		return parent::isAvailable($quote);
		}
	
	public function getOrderPlaceRedirectUrl()
		{
		
		return Mage::getUrl('hcd/', array('_secure' => true));
		}
	
	
	
	public function getHeidelpayUrl($isRegistration=false, $BasketId=false, $RefId=false)
		{
		$config = $frontend = $user = $basketData = array();
		$criterion = array();	
		
		if ($isRegistration === false) {
			$order = Mage::getModel('sales/order');
			$session = $this->getCheckout();
			$order->loadByIncrementId($session->getLastRealOrderId());
			$ordernr = $order->getRealOrderId();
		}else {
			$CustomerId = $this->getCustomerId();
			$visitorData = Mage::getSingleton('core/session')->getVisitorData();
			$ordernr = ( $CustomerId == 0) ? $visitorData['visitor_id'] :  $CustomerId;
			$order = $this->getQuote() ;
		};
		$this->log("Heidelpay Payment Code : ".$this->_code);
		$config = $this->getMainConfig($this->_code);
		if ($isRegistration === true)$config['PAYMENT.TYPE'] = 'RG'; 
		$frontend = $this->getFrontend($ordernr);
		if ($isRegistration === true) $frontend['FRONTEND.SUCCESS_URL'] = Mage::getUrl('hcd/', array('_secure' => true));
		if ($isRegistration === true) $frontend['CRITERION.SHIPPPING_HASH'] =  $this->getShippingHash();
		$user = $this->getUser($order, $isRegistration);
		
		
		if ($isRegistration === false) {
			$completeBasket  = ($config['INVOICEING'] == 1 or $this->_code == "hcdbs") ? true : false;
			$basketData = $this->getBasketData($order, $completeBasket);
		} else {
			
		};
		if  ($RefId !== false ) $user['IDENTIFICATION.REFERENCEID'] = $RefId ;
		if  ($BasketId !== false ) $basketData['BASKET.ID'] = $BasketId ;
		Mage::dispatchEvent('heidelpay_getHeidelpayUrl_bevor_preparePostData', array('order' => $order, 'config' => $config, 'frontend' => $frontend, 'user' => $user, 'basketData' => $basketData, 'criterion' => $criterion ));
		$params = Mage::helper('hcd/payment')->preparePostData( $config, $frontend,	$user, $basketData,
			$criterion);
		$this->log("doRequest url : ".$config['URL'], 'DEBUG');
		$this->log("doRequest params : ".print_r($params,1), 'DEBUG');
		$src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
		$this->log("doRequest response : ".print_r($src,1), 'DEBUG');
		
		return $src;
		}
	
	public function getBasketData($order , $completeBasket = false, $amount=false) {
		$data = array (
			'PRESENTATION.AMOUNT' 			=> ($amount) ? $amount : Mage::helper('hcd/payment')->format($order->getGrandTotal()),
			'PRESENTATION.CURRENCY'			=> $order->getOrderCurrencyCode(),
			'IDENTIFICATION.TRANSACTIONID'	=> $order->getRealOrderId()
		);
		// Add basket details in case of BillSafe or invoicing over heidelpay
		$basket = array();
		if ($completeBasket) {
			$basket  = $this->getBasket($order);
		}
		
		return array_merge($basket, $data);
	}
	
	
	
	public function getFrontend($ordernr, $storeId=false) {
		
		return array(
			'FRONTEND.LANGUAGE'		=> 	Mage::helper('hcd/payment')->getLang(),
			'FRONTEND.RESPONSE_URL' => 	Mage::getUrl('hcd/index/response', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
			'FRONTEND.SUCCESS_URL' 	=>  Mage::getUrl('hcd/index/success', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
			'FRONTEND.FAILURE_URL' 	=>  Mage::getUrl('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
			'CRITERION.PUSH_URL' => 	Mage::getUrl('hcd/index/push', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),  // PUSH proxy is only used for development purpose
			'CRITERION.SECRET'		=> 	Mage::getModel('hcd/resource_encryption')->getHash((string)$ordernr),
			'CRITERION.LANGUAGE'	=>	strtolower(Mage::helper('hcd/payment')->getLang()),
			'CRITERION.STOREID'			=>	($storeId) ? $storeId : Mage::app()->getStore()->getId(),
			'SHOP.TYPE' 			=> 'Magento '. Mage::getVersion(),
			'SHOPMODULE.VERSION' 	=> 'HeidelpayCD Edition - '. (string) Mage::getConfig()->getNode()->modules->HeidelpayCD_Edition->version
		);
	}
	
	public function getUser($order, $isReg=false) {
		
		$user = array();
		$billing	= $order->getBillingAddress();
		$email = ($order->getBillingAddress()->getEmail()) ? $order->getBillingAddress()->getEmail() : $order->getCustomerEmail();
		$CustomerId = $billing->getCustomerId();
		$user['CRITERION.GUEST'] = 'false';
		if ( $CustomerId == 0) {
			$visitorData = Mage::getSingleton('core/session')->getVisitorData();
			$CustomerId	= $visitorData['visitor_id']; 
			$user['CRITERION.GUEST'] = 'true';	
		}
		
		$user['IDENTIFICATION.SHOPPERID'] 	= $CustomerId;
		if ($billing->getCompany() == true) $user['NAME.COMPANY']	= trim($billing->getCompany());
		$user['NAME.GIVEN']			= trim($billing->getFirstname());
		$user['NAME.FAMILY']		= trim($billing->getLastname());
		$user['ADDRESS.STREET']		= trim($billing->getStreet1()." ".$billing->getStreet2());
		$user['ADDRESS.ZIP']		= trim($billing->getPostcode());
		$user['ADDRESS.CITY']		= trim($billing->getCity());
		$user['ADDRESS.COUNTRY']	= trim($billing->getCountry());
		$user['CONTACT.EMAIL']		= trim($email);
		$user['CONTACT.IP']		=  (filter_var(trim(Mage::app()->getRequest()->getClientIp()), FILTER_VALIDATE_IP)) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1' ;
		
		
		//load reconized data 
		
		if ($isReg === false and $order->getPayment()->getMethodInstance()->activRedirct() === true) {
			
				
				if($this->getCustomerData($this->_code, $billing->getCustomerId())) {
					$paymentData = $this->getCustomerData($this->_code, $billing->getCustomerId());
					
					$this->log('getUser Customer: '. print_r($paymentData,1), 'DEBUG');
					
					
					
					// remove SHIPPPING_HASH from parameters
					if (isset($paymentData['payment_data']['SHIPPPING_HASH']))
						unset($paymentData['payment_data']['SHIPPPING_HASH']);
					
					// remove cc or dc reference data
					if ($this->_code == 'hcdcc' or $this->_code == 'hcddc') {	
						if (isset($paymentData['payment_data']['ACCOUNT_BRAND']))
							unset($paymentData['payment_data']['ACCOUNT_BRAND']);
						if (isset($paymentData['payment_data']['ACCOUNT_NUMBER']))
							unset($paymentData['payment_data']['ACCOUNT_NUMBER']);
						if (isset($paymentData['payment_data']['ACCOUNT_HOLDER']))
							unset($paymentData['payment_data']['ACCOUNT_HOLDER']);
						if (isset($paymentData['payment_data']['ACCOUNT_EXPIRY_MONTH']))
							unset($paymentData['payment_data']['ACCOUNT_EXPIRY_MONTH']);
						if (isset($paymentData['payment_data']['ACCOUNT_EXPIRY_YEAR']))
							unset($paymentData['payment_data']['ACCOUNT_EXPIRY_YEAR']);
						
					}
					foreach($paymentData['payment_data'] AS $k => $v )
					$user[$k] = $v;
				}
			
		}
		return $user;	
	}
	
	public function getBasket($order)
		{
		$items = $order->getAllVisibleItems();
		
		if ($items) {
			$i = 0;
			foreach($items as $item) {
				$i++;
				$prefix = 'CRITERION.POS_'.sprintf('%02d', $i);
				$quantity = (int)$item->getQtyOrdered();
				$parameters[$prefix.'.POSITION'] 				= $i;
				$parameters[$prefix.'.QUANTITY'] 				= $quantity; 
				$parameters[$prefix.'.UNIT'] 					= 'Stk.'; // Liter oder so
				$parameters[$prefix.'.AMOUNT_UNIT_GROSS'] 		= floor(bcmul($item->getPriceInclTax(), 100, 10));
				$parameters[$prefix.'.AMOUNT_GROSS'] 			= floor(bcmul($item->getPriceInclTax() * $quantity, 100, 10));
				
				
				$parameters[$prefix.'.TEXT'] 					= $item->getName();
				$parameters[$prefix.'.COL1'] 					= 'SKU:'.$item->getSku();
				$parameters[$prefix.'.ARTICLE_NUMBER'] 			= $item->getProductId();
				$parameters[$prefix.'.PERCENT_VAT'] 			= sprintf('%1.2f', $item->getTaxPercent());
				$parameters[$prefix.'.ARTICLE_TYPE'] 			= 'goods';
			}
		}
		
		if ($this->getShippingNetPrice($order) > 0){
			$i++;
			$prefix = 'CRITERION.POS_'.sprintf('%02d', $i);
			$parameters[$prefix.'.POSITION'] 					= $i;
			$parameters[$prefix.'.QUANTITY'] 					= '1';
			$parameters[$prefix.'.UNIT'] 						= 'Stk.'; // Liter oder so
			$parameters[$prefix.'.AMOUNT_UNIT_GROSS'] 			= floor(bcmul((($order->getShippingAmount() - $order->getShippingRefunded()) * (1 + $this->getShippingTaxPercent($order)/100)), 100, 10));
			$parameters[$prefix.'.AMOUNT_GROSS'] 				= floor(bcmul((($order->getShippingAmount() - $order->getShippingRefunded()) * (1 + $this->getShippingTaxPercent($order)/100)), 100, 10));
			
			$parameters[$prefix.'.TEXT'] 						= 'Shipping';
			$parameters[$prefix.'.ARTICLE_NUMBER'] 				= '0';
			$parameters[$prefix.'.PERCENT_VAT'] 				=  $this->getShippingTaxPercent($order);
			$parameters[$prefix.'.ARTICLE_TYPE'] 				= 'shipment';
		}
		
		if ($order->getDiscountAmount() < 0){
			$i++;
			$prefix = 'CRITERION.POS_'.sprintf('%02d', $i);
			$parameters[$prefix.'.POSITION'] 					= $i;
			$parameters[$prefix.'.QUANTITY'] 					= '1';
			$parameters[$prefix.'.UNIT'] 						= 'Stk.'; // Liter oder so
			$parameters[$prefix.'.AMOUNT_UNIT_GROSS'] 			= floor(bcmul($order->getDiscountAmount(), 100, 10));
			$parameters[$prefix.'.AMOUNT_GROSS'] 				= floor(bcmul($order->getDiscountAmount(), 100, 10));
			
			$parameters[$prefix.'.TEXT'] 						= 'Voucher';
			$parameters[$prefix.'.ARTICLE_NUMBER'] 				= '0';
			$parameters[$prefix.'.PERCENT_VAT'] 				= '0.00';
			$parameters[$prefix.'.ARTICLE_TYPE'] 				= 'voucher';
		}
		
		return $parameters;
		}
	
	protected function getShippingTaxPercent($order)
		{
		$tax = ($order->getShippingTaxAmount() * 100) / $order->getShippingAmount();
		return Mage::helper('hcd/payment')->format(round($tax));
		}
	
	protected function getShippingNetPrice($order)
		{
		$shippingTax = $order->getShippingTaxAmount();
		$price = $order->getShippingInclTax() - $shippingTax;
		$price -= $order->getShippingRefunded();
		$price -= $order->getShippingCanceled();
		return $price;
		}
	
	
	/**
	 * Retrieve information from payment configuration
	 *
	 * @param   string $field
	 * @return  mixed
	 */
	public function getMainConfig($code, $storeId=false)
		{
		$storeId = ($storeId) ? $storeId : $this->getStore();
		$path = "hcd/settings/";
		$config = array();
		$config['PAYMENT.METHOD'] = preg_replace('/^hcd/','',$code);
		$config['SECURITY.SENDER'] = Mage::getStoreConfig($path."security_sender", $storeId);
		if(Mage::getStoreConfig($path."transactionmode", $storeId) == 0) {
			$config['TRANSACTION.MODE'] = 'LIVE';
			$config['URL']	= $this->_live_url ;
			
		} else {
			$config['TRANSACTION.MODE'] = 'CONNECTOR_TEST';
			$config['URL']	= $this->_sandbox_url ;
		}
		$config['USER.LOGIN'] = trim(Mage::getStoreConfig($path."user_id", $storeId));
		$config['USER.PWD'] = trim(Mage::getStoreConfig($path."user_pwd", $storeId));
		$config['INVOICEING'] = (Mage::getStoreConfig($path."invoicing", $storeId) == 1) ? 1 : 0 ;	 	
		$config['USER.PWD'] = trim(Mage::getStoreConfig($path."user_pwd", $storeId));
		
		$path = "payment/".$code."/";
		$config['TRANSACTION.CHANNEL'] =  trim(Mage::getStoreConfig($path."channel", $storeId));
		(Mage::getStoreConfig($path."bookingmode", $storeId) == true) ?  $config['PAYMENT.TYPE'] = Mage::getStoreConfig($path."bookingmode", $storeId) : false ;
		
		return $config;
		}
	
	
	public function getTitle(){
		$storeId = $this->getStore();
		$path = "payment/".$this->_code."/";
		return $this->_getHelper()->__(Mage::getStoreConfig($path."title", $storeId));
	}
	
	public function getAdminTitle(){
		$storeId = $this->getStore();
		$path = "payment/".$this->_code."/";
		return $this->_getHelper()->__(Mage::getStoreConfig($path."title", $storeId));
	}
	
	
	public function canCapture() {
		
		//check wether this payment method supports capture
			
		if($this->_canCapture === false ) return false ;
		
		// prevent frontent to capture an amount in case of direct booking with automatical invoice
		if (Mage::app()->getStore()->getId() != 0) { 
			$this->log('try to capture amount in frontend ... this is not necessary !');
			return false;
		}	
		
		
		// loading order object to check wether this 
		$orderIncrementId =  Mage::app()->getRequest()->getParam('order_id');
		$this->log('$orderIncrementId '.$orderIncrementId);
		$order = Mage::getModel('sales/order');
		$order->loadByAttribute('entity_id', (int)$orderIncrementId);	
				
		if (Mage::getModel('hcd/transaction')->getOneTransactionByMethode($order->getRealOrderId() , 'PA') === false) {
			$this->log('there is no preauthorisation for the order '.$order->getRealOrderId());
			return false;
		}
		
		return true;
	}
	
	
	public function capture(Varien_Object $payment, $amount)
		{
		$criterion=array();
		
		$order = $payment->getOrder();
		$this->log('StoreId'.$order->getStoreId());
		$Autorisation = array();
		if ($this->canCapture()) { 
				
			$Autorisation = Mage::getModel('hcd/transaction')->getOneTransactionByMethode($order->getRealOrderId() , 'PA');
			
			
			if ($Autorisation === false) {
				Mage::throwException(Mage::helper('hcd')->__('This Transaction could not be capture online.')); 	
				return $this;
			}
			
			$config = $this->getMainConfig($this->_code, $Autorisation['CRITERION_STOREID']);
			$config['PAYMENT.TYPE']		= 'CP';
			
			
			$frontend = $this->getFrontend($order->getRealOrderId(), $Autorisation['CRITERION_STOREID']);
			$frontend['FRONTEND.MODE'] 		= 'DEFAULT';
			$frontend['FRONTEND.ENABLED'] 	= 'false';
			
			$user = $this->getUser($order, true);
			$basketdetails = ($this->_code == 'hcdbs') ? true : false; // If billsafe set to fin
			$basketData = $this->getBasketData($order, $basketdetails , $amount);
			
			$basketData['IDENTIFICATION.REFERENCEID'] = $Autorisation['IDENTIFICATION_UNIQUEID'];
			Mage::dispatchEvent('heidelpay_capture_bevor_preparePostData', array('payment' => $payment, 'config' => $config, 'frontend' => $frontend, 'user' => $user, 'basketData' => $basketData, 'criterion' => $criterion ));
			$params = Mage::helper('hcd/payment')->preparePostData( $config, $frontend,	$user, $basketData,
				$criterion);
			
			
			
			$this->log("doRequest url : ".$config['URL']);
			$this->log("doRequest params : ".print_r($params,1));
			
			$src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
			
			$this->log("doRequest response : ".print_r($src,1));
			//Mage::throwException('Heidelpay Error: '.'<pre>'.print_r($src,1).'</pre>');
			
			
			if($src['PROCESSING_RESULT'] == "NOK") {
				Mage::throwException('Heidelpay Error: '.$src['PROCESSING_RETURN']);
				return $this;;
			}
			
			$payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
			Mage::getModel('hcd/transaction')->saveTransactionData($src);
		}
		return $this;
		}
	
	public function refund(Varien_Object $payment, $amount)
		{
		$order = $payment->getOrder();
		
		$CaptureData = Mage::getModel('hcd/transaction')->loadLastTransactionDataByUniqeId((string)$payment->getRefundTransactionId());
		
		$config = $this->getMainConfig($this->_code, $CaptureData['CRITERION_STOREID']);
		$config['PAYMENT.TYPE'] = 'RF';
		$frontend = $this->getFrontend($order->getRealOrderId(), $CaptureData['CRITERION_STOREID']);
		$frontend['FRONTEND.MODE'] 		= 'DEFAULT';
		$frontend['FRONTEND.ENABLED'] 	= 'false';
		$user = $this->getUser($order, true);
		$basketData = $this->getBasketData($order, false , $amount);
		$basketData['IDENTIFICATION.REFERENCEID'] = (string)$payment->getRefundTransactionId();
		$params = Mage::helper('hcd/payment')->preparePostData( $config, $frontend,	$user, $basketData,
			$criterion=array());
		$this->log("doRequest url : ".$config['URL']);
		$this->log("doRequest params : ".print_r($params,1));
		
		$src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
		$this->log("doRequest response : ".print_r($src,1));
		if($src['PROCESSING_RESULT'] == "NOK") {
			Mage::throwException('Heidelpay Error: '.$src['PROCESSING_RETURN']);
			return $this;;
		}
		$payment->setTransactionId($src['IDENTIFICATION_UNIQUEID']);
		Mage::getModel('hcd/transaction')->saveTransactionData($src);
		return $this;;
		}
	
	
	private function restock($order){
		$session = $this->getSession();
		if(floatval(substr(Mage::getVersion(),0,-4)) <= floatval('1.7')){
			
			if ($session->getStockUpdated() != $session->getLastRealOrderId()){
				$items = $order->getAllItems();
				if ($items){
					foreach($items as $item){
						$quantity = $item->getQtyOrdered();
						$product_id = $item->getProductId();
						// load stock for product
						$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
						// set to old qty
						$stock->setQty($stock->getQty() + $quantity)->setIsInStock(true);
						$stock->save();
					}
				}
				$session->setStockUpdated($session->getLastRealOrderId());
			}
		} 
	}
	public function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  Mage::helper('hcd/payment')->realLog( $callers[1]['function'].' '. $message , $level , $file);
	}
	
	function getCustomerName($session=false)
		{
		if ( $session === true ) {
			$session = $this->getCheckout();
			return $session->getQuote()->getBillingAddress()->getFirstname().' '.$session->getQuote()->getBillingAddress()->getLastname();
		}
		
		return $this->getQuote()->getBillingAddress()->getFirstname().' '.$this->getQuote()->getBillingAddress()->getLastname();
		}
	
	public function saveCustomerData($data, $uniqeID=NULL) {
		$custumerData = Mage::getModel('hcd/customer');
		
		if ($this->getCustomerData() !== false) {
			$lastdata = $this->getCustomerData();
			$custumerData->load($lastdata['id']);	
		}
		
		$this->log('StoreID :'.Mage::app()->getStore()->getId());
		$CustomerId = $this->getQuote()->getBillingAddress()->getCustomerId();
		$StoreId = Mage::app()->getStore()->getId();
		if ( $CustomerId == 0) {
			$visitorData = Mage::getSingleton('core/session')->getVisitorData();
			$CustomerId	= $visitorData['visitor_id']; 
			$StoreId = 0;	
		}
		
		
		$custumerData->setPaymentmethode($this->_code);
		$custumerData->setUniqeid($uniqeID);
		$custumerData->setCustomerid($CustomerId);
		$custumerData->setStoreid($StoreId);
		$data['SHIPPPING_HASH'] =  $this->getShippingHash();
		$custumerData->setPaymentData(Mage::getModel('hcd/resource_encryption')->encrypt(json_encode($data)));
		
		$custumerData->save();
		
	}
	
	function getCustomerData($code=false, $customerId=false, $storeId=false ) {
		
		$PaymentCode = ($code)? $code : $this->_code ;
		$CustomerId  = ($customerId) ? $customerId : $this->getQuote()->getBillingAddress()->getCustomerId() ;
		$StoreId	=	($storeId) ? $storeId : Mage::app()->getStore()->getId();
		if ( $CustomerId == 0) {
			$visitorData = Mage::getSingleton('core/session')->getVisitorData();
			$CustomerId	= $visitorData['visitor_id']; 
			$StoreId = 0;	
		}
		
		$this->log('StoreID :'.Mage::app()->getStore()->getId());	 
		
		$custumerData = Mage::getModel('hcd/customer')			
			->getCollection()
			->addFieldToFilter('Customerid', $CustomerId)
			->addFieldToFilter('Storeid', $StoreId)
			->addFieldToFilter('Paymentmethode', $PaymentCode );
		
		$custumerData->load();
		$data = $custumerData->getData();
		
		/* retun false if not */
		if (empty($data[0]['id'])) return false;
		
		$return = array();
		
		$return['id'] = $data[0]['id']; 
		
		if (!empty($data[0]['uniqeid'])) $return['uniqeid'] = $data[0]['uniqeid'];
		if (!empty($data[0]['payment_data'])) $return['payment_data'] = json_decode(Mage::getModel('hcd/resource_encryption')->decrypt($data[0]['payment_data']), true);
		return $return ;
		
	}
	
	function getShippingHash() {
		$shipping = $this->getQuote()->getShippingAddress();
		return  md5(	$shipping->getFirstname().
			$shipping->getLastname().
			$shipping->getStreet1()." ".$shipping->getStreet2().
			$shipping->getPostcode().
			$shipping->getCity().
			$shipping->getCountry()
		);
		
		
	}
	
	function getCustomerId() {
		return $this->getQuote()->getBillingAddress()->getCustomerId() ;
	}
	
	public function showPaymentInfo($payment_data) {
		/* 
		 * This function should not be modified please overright this function
		 * in the class of the used payment methode !!!
		 * 
		 * your function should set $this->getCheckout()->setHcdPaymentInfo($userMessage)
		 */
		
		return false;
	}
}

