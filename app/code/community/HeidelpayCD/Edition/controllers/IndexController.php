<?php
class HeidelpayCD_Edition_IndexController extends Mage_Core_Controller_Front_Action
{
	protected $_sendNewOrderEmail   = TRUE;
	protected $_invoiceOrderEmail   = TRUE;
	protected $_order               = NULL;
	protected $_paymentInst         = NULL;
	protected $_debug				= TRUE;
	
	protected $_live_url 	= 'https://heidelpay.hpcgw.net/ngw/post';
	protected $_sandbox_url = 'https://test-heidelpay.hpcgw.net/ngw/post';
	
	
	protected $_live_basket_url 	= 'https://heidelpay.hpcgw.net/ngw/basket/';
	protected $_sandbox_basket_url  = 'https://test-heidelpay.hpcgw.net/ngw/basket/';
	
	
	public $importantPPFields = array(
		'PRESENTATION_AMOUNT', 
		'PRESENTATION_CURRENCY', 
		'CONNECTOR_ACCOUNT_COUNTRY', 
		'CONNECTOR_ACCOUNT_HOLDER', 
		'CONNECTOR_ACCOUNT_NUMBER', 
		'CONNECTOR_ACCOUNT_BANK',
		'CONNECTOR_ACCOUNT_BIC', 
		'CONNECTOR_ACCOUNT_IBAN', 
		'IDENTIFICATION_SHORTID',
	);
	
	public function preDispatch() 
		{
		parent::preDispatch();
		
		/* Review because of fail behavior in case of gueste buyer TODO
		 $action = $this->getRequest()->getActionName();
		 if ($action != 'response')  
		 {
		 if (!Mage::getSingleton('customer/session')->authenticate($this))
		 {
		 $this->getResponse()->setRedirect(Mage::helper('customer')->getLoginUrl());
		 $this->setFlag('', self::FLAG_NO_DISPATCH, true);
		 return $this;					
		 }
		 }
		 */
		
		}
	
	protected function _getHelper()
		{
		return Mage::helper('hcd');
		}
	
	private function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  Mage::helper('hcd/payment')->realLog( $callers[1]['function'].' '.$message , $level , $file);
	}
	
	protected function _expireAjax()
		{
		if (!$this->getCheckout()->getQuote()->hasItems()) {
			$this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
			exit;
		}
		}
	
	
	/**
	 * Get order model
	 *
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
		{
		return Mage::getModel('sales/order');
		}
	
	/**
	 * Get checkout session namespace
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	public function getCheckout()
		{
		return Mage::getSingleton('checkout/session');
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
	 * Get hp session namespace
	 *
	 * @return Mage_Heidelpay_Model_Session
	 */
	public function getSession()
		{
		return Mage::getSingleton('core/session');
		}
	
	protected function _getCheckout()
		{
		return Mage::getSingleton('checkout/session');
		}
	
	 public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }
    
    public function getStore() {
    	return Mage::app()->getStore()->getId();
    }
	
	/**
	 * successful return from Heidelpay payment 
	 */
	public function successAction()
		{
		$session = $this->getCheckout();
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($session->getLastRealOrderId());
		if($order->getPayment() === false) {
			$this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
			return $this;
		}
		
		//$this->log('No Mail '.Mage::app()->getRequest()->getParam('no_mail'));
		$no_mail = (Mage::app()->getRequest()->getParam('no_mail') == 1) ? true : false;
		
		
		$this->getCheckout()->getQuote()->setIsActive(false)->save();
		$this->getCheckout()->clear();
		
		$message = "";
		
		$data = Mage::getModel('hcd/transaction')->loadLastTransactionDataByTransactionnr($session->getLastRealOrderId());
		
		/*
		 * validate Hash to prevent manipulation
		 */
		if (Mage::getModel('hcd/resource_encryption')->validateHash($data['IDENTIFICATION_TRANSACTIONID'],$data['CRITERION_SECRET']) === false) {
			$this->log("Customer tries to redirect directly to success page. IP " . Mage::app()->getRequest()->getServer('REMOTE_ADDR') . " . This could be some kind of manipulation.", 'WARN');
			$this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
		};
		
		$session->unsHcdPaymentInfo();
		
		if ($order->getPayment()->getMethodInstance()->getCode() != 'hcdiv') {
			$info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
			if ($info !== false) {
				$session->setHcdPaymentInfo($info);
				$order->setCustomerNote($info);
			}
		}
		
		$quoteID = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId() ; // last_quote_id workaround for trusted shop buyerprotection
		$this->getCheckout()->setLastSuccessQuoteId($quoteID);
		$this->log('LastQuteID :'. $quoteID );
		
		if ($no_mail === false) {
			Mage::helper('hcd/payment')->mapStatus (
				$data,
				$order
			); 
		}
		
		if($order->getId() and $no_mail === false) { 
			$order->sendNewOrderEmail();
			//$this->log('sendOrderMail');
		}
		$order->save();
		$this->_redirect('checkout/onepage/success', array('_secure' => true));
		return;
		}
		
	public function errorAction()
		{
		$session = $this->getCheckout();
		$errorCode	=	null;
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($session->getLastRealOrderId());
		$this->log(' LastRealOrderId '.print_r($session->getLastRealOrderId(),1));
		
		$Request = Mage::app()->getRequest();
		$GET_ERROR =  $Request->getParam('HPError');
		
		
		
		
		// in case of an error in the server to server request 
		$usersession = $this->getSession();
		// var_dump($usersession->getHcdError());
		// exit;
		$data = Mage::getModel('hcd/transaction')->loadLastTransactionDataByTransactionnr($session->getLastRealOrderId());
		$this->log(' data '.print_r($data,1));
		
		if($usersession->getHcdError() !== NULL ) {
			$message = Mage::helper('hcd/payment')->handleError($usersession->getHcdError(),$errorCode,(string)$order->getRealOrderId());
			$intMessage = $usersession->getHcdError();
			$usersession->unsHcdError();
		} else {
			if (isset($data['PROCESSING_RETURN_CODE'])) $errorCode = $data['PROCESSING_RETURN_CODE'];
			if (isset($GET_ERROR)) { 
										$errorCode = $GET_ERROR;
										$data['PROCESSING_RESULT'] = 'NOK';							
			}
			$message = Mage::helper('hcd/payment')->handleError($data['PROCESSING_RETURN'],$errorCode,(string)$order->getRealOrderId());
			$intMessage = (!empty($data['PROCESSING_RETURN'])) ? $data['PROCESSING_RETURN'] : $message ;
		}
		
		$quoteId = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId() ; // last_quote_id workaround for trusted shop buyerprotection
		if ($quoteId) {
			$quote = Mage::getModel('sales/quote')->load($quoteId);
			if ($quote->getId()) {
				$quote->setIsActive(true)->save();
				$session->setQuoteId($quoteId);
			}
		}
		
		Mage::helper('hcd/payment')->mapStatus (
			$data,
			$order,
			$intMessage
		); 
		
		$storeId = Mage::app()->getStore()->getId();
		$redirectController = Mage::getStoreConfig("hcd/settings/returnurl", $storeId);
		
		switch ($redirectController){
			case "basket":
				$session->addError($message);
				$this->_redirect('checkout/cart', array('_secure' => true));
				break;
				/*
				 case "onestepcheckout":
				 $session->addError($message);
				 $this->_redirect('onestepcheckout/', array('_secure' => true));
				 break;
				 */	
			default:
				$usersession->addError($message);
			$this->_redirect('checkout/onepage', array('_secure' => true));
		}
		
		}
	/**
	 * redirect return from Heidelpay payment (iframe)
	 */
	public function indexAction()
		{
		$data = array();
		$order = $this->getOrder();
		
		$RefId 		= false;
		$BasketId 	= false;
		
		$session = $this->getCheckout();
		$order->loadByIncrementId($session->getLastRealOrderId());
		if ($order->getPayment() === false ) {
			$this->getResponse()->setRedirect(Mage::helper('customer')->getLoginUrl());
			$this->setFlag('', self::FLAG_NO_DISPATCH, true);
			return $this;					
		}
		$payment = $order->getPayment()->getMethodInstance();
		
		if ($session->getHcdWallet() !== false) {
		$wallet = $session->getHcdWallet();
		$RefId = (!empty($wallet['referenceId'])) ? $wallet['referenceId'] : false;
		$this->log('Wallet reference id :'.$RefId);
		}
		

		if($payment->_canBasketApi == true and empty($RefId)) {
			
			$ShoppingCart = Mage::helper('hcd/payment')->basketItems($order, $this->getStore());
        
       		$url = (Mage::getStoreConfig('hcd/settings/transactionmode', $this->getStore()) == 0) ? $this->_live_basket_url : $this->_sandbox_basket_url;
       	
	 		$this->log("doRequest shoppingcart : ".print_r($ShoppingCart,1), 'DEBUG');
	 	
	 		$result = Mage::helper('hcd/payment')->doRequest($url, array( 'raw' => $ShoppingCart));
		
			if (array_key_exists('result', $result) && $result['result'] == 'NOK'){
				$this->log('Send basket to payment  fail, because of : '.print_r($result,1),'ERROR');
				Mage::getSingleton('core/session')->setHcdError($result['basketErrors']['message']);
				$this->_redirect('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));	
				return;
			}
		
			$this->log("doRequest shoppingcart response : ".print_r($result,1), 'DEBUG');
			$BasketId	= (array_key_exists('basketId',$result)) ? $result['basketId'] : false ;
			
			
		}
		
		
		// if order status is cancel redirect to cancel page 
		if ($order->getStatus() == $payment->getStatusError()) {
				$this->_redirect('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
				return;
		};
		
		// if order status is success redirect to success page 
		if ($order->getStatus() == $payment->getStatusSuccess() or $order->getStatus() == $payment->getStatusPendig()) {
			$this->_redirect('hcd/index/success', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true,'no_mail' => true));
			return;
		}
   
			
		
		$data = $payment->getHeidelpayUrl(false , $BasketId, $RefId);
		
		if($data['POST_VALIDATION'] == 'ACK' and $data['PROCESSING_RESULT'] == 'ACK' ) 
		{
			if($data['PAYMENT_CODE'] == "OT.PA" ) {
				$quoteID = ($session->getLastQuoteId() === false) ? $session->getQuoteId() : $session->getLastQuoteId() ; // last_quote_id workaround for trusted shop buyerprotection
				$order->getPayment()->setTransactionId($quoteID);
				$order->getPayment()->setIsTransactionClosed(true);
			}
			$order->setState( 	$order->getPayment()->getMethodInstance()->getStatusPendig(false),
				$order->getPayment()->getMethodInstance()->getStatusPendig(true),
				Mage::helper('hcd')->__('Get payment url from Heidelpay -> ').$data['FRONTEND_REDIRECT_URL'] );
			$order->getPayment()->addTransaction(
				Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
				null,
				true
			);	
			$order->save();
			
			$session->getQuote()->setIsActive(true)->save();
			$session->clear();
			
			if ( $payment->activRedirct() === true ) {
				$this->_redirectUrl($data['FRONTEND_REDIRECT_URL']);
			} 
			$this->loadLayout();
			$this->log('RedirectUrl ' .$data['FRONTEND_REDIRECT_URL'] );
			$this->log('CCHolder ' .$payment->getCustomerName() );
			$this->getLayout()->getBlock('hcd_index')->setHcdUrl($data['FRONTEND_REDIRECT_URL']);
			$this->getLayout()->getBlock('hcd_index')->setHcdCode($payment->getCode());
			$this->getLayout()->getBlock('hcd_index')->setHcdBrands($data['CONFIG_BRANDS']);
			$this->getLayout()->getBlock('hcd_index')->setHcdHolder($payment->getCustomerName(true));
		} else {
			Mage::getModel('hcd/transaction')->saveTransactionData($data);		
			Mage::getSingleton('core/session')->setHcdError($data['PROCESSING_RETURN']);
			$this->_redirect('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));	
		}
		
		
		
		$this->renderLayout();
		return $this ;
		}
	
	public function walletAction() 
	{
		$data = array();
		$Request = Mage::app()->getRequest();
		$paymentCode = $Request->getParam('_wallet');
		$storeId = $this->getStore();
		$code = false;
		$mageBasketId = (string)$this->getCheckout()->getQuoteId();
		
		if ($paymentCode == 'hcdmpa') {
			$code = 'hcdmpa';
		};
		
		$quote = $this->getOnepage()->getQuote();
        if (!$quote->hasItems() || $quote->getHasError() || $code === false ) {
            $this->_redirect('checkout/cart');
            return;
        }
        
        
		$ShoppingCart = Mage::helper('hcd/payment')->basketItems($quote, $storeId);
        
       	$url = (Mage::getStoreConfig('hcd/settings/transactionmode', $storeId) == 0) ? $this->_live_basket_url : $this->_sandbox_basket_url;
       	
	 	$this->log("doRequest shoppingcart : ".print_r($ShoppingCart,1), 'DEBUG');
	 	$this->log("doRequest shoppingcart : ".print_r(json_encode($ShoppingCart),1), 'DEBUG');
	 	
	 	$result = Mage::helper('hcd/payment')->doRequest($url, array( 'raw' => $ShoppingCart));
		
		if (array_key_exists('result', $result) && $result['result'] == 'NOK'){
			$this->log('Send basket to payment  fail, because of : '.print_r($result,1),'ERROR');
			$message = $this->_getHelper()->__('An unexpected error occurred. Please contact us to get further information.');		
			Mage::getSingleton('core/session')->addError($message);
			$this->_redirect('checkout/cart', array('_secure' => true));
			return;	
		}
		
		$this->log("doRequest shoppingcart response : ".print_r($result,1), 'DEBUG');
		
		$config 		= array(	'PAYMENT.METHOD'		=> preg_replace('/^hcd/','',$code ),
        							'SECURITY.SENDER'		=> Mage::getStoreConfig('hcd/settings/security_sender', $storeId),
        							'TRANSACTION.MODE'		=> (Mage::getStoreConfig('hcd/settings/transactionmode', $storeId) == 0) ? 'LIVE' : 'CONNECTOR_TEST' ,
        							'URL'					=> (Mage::getStoreConfig('hcd/settings/transactionmode', $storeId) == 0) ? $this->_live_url : $this->_sandbox_url ,
        							'USER.LOGIN'			=> trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
        							'USER.PWD'				=> trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),
        							'TRANSACTION.CHANNEL'	=> trim(Mage::getStoreConfig('payment/'.$code.'/channel', $storeId)),
        							'PAYMENT.TYPE'			=> 'IN'
         );
         $frontend 		= array( 	'FRONTEND.LANGUAGE'		=> 	Mage::helper('hcd/payment')->getLang(),
									'FRONTEND.RESPONSE_URL' => 	Mage::getUrl('hcd/index/response', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
									//'FRONTEND.SUCCESS_URL' 	=>  Mage::getUrl('hcd/index/success', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
									//'FRONTEND.FAILURE_URL' 	=>  Mage::getUrl('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true)),
									'CRITERION.SECRET'		=> 	Mage::getModel('hcd/resource_encryption')->getHash($mageBasketId),
									'CRITERION.LANGUAGE'	=>	strtolower(Mage::helper('hcd/payment')->getLang()),
									'CRITERION.STOREID'		=>	$storeId,
									'SHOP.TYPE' 			=> 'Magento '. Mage::getVersion(),
    								'SHOPMODULE.VERSION' 	=> 'HeidelpayCD Edition - '. (string) Mage::getConfig()->getNode()->modules->HeidelpayCD_Edition->version,
         							'WALLET.DIRECT_PAYMENT' =>	'false'
        );
        
        $visitorData = Mage::getSingleton('core/session')->getVisitorData();
		
        $user			= array(	'IDENTIFICATION.SHOPPERID' 	=> $visitorData['visitor_id'],
									'NAME.GIVEN'				=> ' - ',
									'NAME.FAMILY'				=> ' - ',
									'ADDRESS.STREET'			=> ' - ',
									'ADDRESS.ZIP'				=> ' - ',
									'ADDRESS.CITY'				=> ' - ',
									'ADDRESS.COUNTRY'			=> 'DE',
									'CONTACT.EMAIL'				=> 'dummy@heidelpay.de',
									'CONTACT.IP'				=>  (filter_var(trim(Mage::app()->getRequest()->getClientIp()), FILTER_VALIDATE_IP)) ? trim(Mage::app()->getRequest()->getClientIp()) : '127.0.0.1' 
        );
        
        $basketData = array ( 	'PRESENTATION.AMOUNT' 			=> Mage::helper('hcd/payment')->format($quote->getGrandTotal()) ,
							  	'PRESENTATION.CURRENCY'			=> $quote->getGlobalCurrencyCode(),
								'IDENTIFICATION.TRANSACTIONID'	=> $mageBasketId,
								'BASKET.ID'						=> (array_key_exists('basketId',$result)) ? $result['basketId'] : ''
		);
        
        $params = Mage::helper('hcd/payment')->preparePostData( $config, $frontend,	$user, $basketData ,$criterion = array());
        
        
        $this->log("doRequest url : ".$config['URL'], 'DEBUG');
		$this->log("doRequest params : ".print_r($params,1), 'DEBUG');
		$data = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
		$this->log("doRequest response : ".print_r($data,1), 'DEBUG');
        
        
        if($data['POST_VALIDATION'] == 'ACK' and $data['PROCESSING_RESULT'] == 'ACK' ) 
		{
			/** Redirect on Success */
			//print $data['FRONTEND_REDIRECT_URL'] ;
			//exit(); 
			$this->_redirectUrl(trim($data['FRONTEND_REDIRECT_URL']));
			return;
		} else {
			/** Error Case */
			$this->log('Wallet Redirect for '.$code.' fail, because of : '.$data['PROCESSING_RETURN'],'ERROR');
			$message = $this->_getHelper()->__('An unexpected error occurred. Please contact us to get further information.');		
			Mage::getSingleton('core/session')->addError($message);
			$this->_redirect('checkout/cart', array('_secure' => true));
			return;	
		}
		
	}
	
	/**
	 * response from Heidelpay payment 
	 */
	public function responseAction()
		{
		/*
		 * collect variables
		 */
		$Request = Mage::app()->getRequest();
		$Request->setParamSources(array('_POST'));
		$FRONTEND_SESSIONID				= $Request->getPost('CRITERION_SECRET');
		$data = array();
		$IDENTIFICATION_TRANSACTIONID = $Request->getPOST('IDENTIFICATION_TRANSACTIONID');
		$data['IDENTIFICATION_TRANSACTIONID'] 	= (!empty($IDENTIFICATION_TRANSACTIONID)) ? $Request->getPOST('IDENTIFICATION_TRANSACTIONID') : $Request->getPOST('IDENTIFICATION_SHOPPERID') ;
		
		/*
		 * validate Hash to prevent manipulation
		 */
		if (Mage::getModel('hcd/resource_encryption')->validateHash($data['IDENTIFICATION_TRANSACTIONID'],$FRONTEND_SESSIONID) === false) {
			echo  Mage::getUrl('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
			$this->log("Get response form server " . $Request->getServer('REMOTE_ADDR') . " with an invalid hash. This could be some kind of manipulation.", 'WARN');
			exit();
		};
		
		$data= $Request->getParams();
		
		
		$data['PROCESSING_RESULT'] 				= $Request->getPOST('PROCESSING_RESULT');
		$data['IDENTIFICATION_TRANSACTIONID'] 	= $Request->getPOST('IDENTIFICATION_TRANSACTIONID');
		$data['PROCESSING_STATUS_CODE']			= $Request->getPOST('PROCESSING_STATUS_CODE');
		$data['PROCESSING_RETURN']				= $Request->getPOST('PROCESSING_RETURN');
		$data['PROCESSING_RETURN_CODE']			= $Request->getPOST('PROCESSING_RETURN_CODE');
		$data['PAYMENT_CODE']					= $Request->getPOST('PAYMENT_CODE');
		$data['IDENTIFICATION_UNIQUEID']		= $Request->getPOST('IDENTIFICATION_UNIQUEID');
		$data['FRONTEND_SUCCESS_URL']			= $Request->getPOST('FRONTEND_SUCCESS_URL');
		$data['FRONTEND_FAILURE_URL']			= $Request->getPOST('FRONTEND_FAILURE_URL');
		$data['IDENTIFICATION_SHORTID'] 		= $Request->getPOST('IDENTIFICATION_SHORTID');
		$data['IDENTIFICATION_SHOPPERID'] 		= $Request->getPOST('IDENTIFICATION_SHOPPERID');
		$data['CRITERION_GUEST'] 				= $Request->getPOST('CRITERION_GUEST');
		
		$PaymentCode = Mage::helper('hcd/payment')->splitPaymentCode ($data['PAYMENT_CODE']);
		
		$this->log("Post params: " . print_r($data,1));
		
		if ($PaymentCode[1] == 'RG') {
			if ($data['PROCESSING_RESULT'] == 'NOK'){
				$url = Mage::getUrl('hcd/index/error', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true, 'HPError' => $data['PROCESSING_RETURN_CODE']));
			}else {
				
				// save cc and dc registration data
				$custumerData = Mage::getModel('hcd/customer');
				$currentPaymnet = 'hcd'.strtolower($PaymentCode[0]);
				$Storeid = ($data['CRITERION_GUEST']  == 'true') ? 0 :  trim($data['CRITERION_STOREID']);
				$RgData = Mage::getModel('hcd/customer')			
						->getCollection()
								->addFieldToFilter('Customerid', trim($data['IDENTIFICATION_SHOPPERID']))
								->addFieldToFilter('Storeid', $Storeid)
								->addFieldToFilter('Paymentmethode', trim($currentPaymnet));
				$RgData->load();
				$returnData = $RgData->getData();
				if (!empty($returnData[0]['id'])) $custumerData->setId((int)$returnData[0]['id']);  	
				
				$custumerData->setPaymentmethode($currentPaymnet);
				$custumerData->setUniqeid($data['IDENTIFICATION_UNIQUEID']);
				$custumerData->setCustomerid($data['IDENTIFICATION_SHOPPERID']);
				$custumerData->setStoreid($Storeid);
				$custumerData->setPaymentData(
								Mage::getModel('hcd/resource_encryption')
									->encrypt(json_encode(
										array( 
											'ACCOUNT.REGISTRATION'    => $data['IDENTIFICATION_UNIQUEID'],
											'SHIPPPING_HASH'		  => $data['CRITERION_SHIPPPING_HASH'],
											'ACCOUNT_BRAND'			  => $data['ACCOUNT_BRAND'],
											'ACCOUNT_NUMBER'		  => $data['ACCOUNT_NUMBER'],
											'ACCOUNT_HOLDER'		  => $data['ACCOUNT_HOLDER'],
											'ACCOUNT_EXPIRY_MONTH'	  => $data['ACCOUNT_EXPIRY_MONTH'],
											'ACCOUNT_EXPIRY_YEAR'	  => $data['ACCOUNT_EXPIRY_YEAR']
											))));
											
				$custumerData->save();
				
			$url = Mage::getUrl('hcd/', array('_secure' => true));
			
			}
			} elseif ($PaymentCode[1] == 'IN' and $Request->getPost('WALLET_DIRECT_PAYMENT') == 'false') {

			// Back to checkout after wallet init
			if ($data['PROCESSING_RESULT'] == 'NOK'){
					$this->log('Wallet for basketId '.$data['IDENTIFICATION_TRANSACTIONID'].' failed because of '.$data['PROCESSING_RETURN'],'NOTICE');	
					$url = Mage::getUrl('checkout/cart', array('_secure' => true));
			}else {
					$url = Mage::getUrl('hcd/checkout/', array('_secure' => true, '_wallet' => 'hcdmpa'));
			}
			Mage::getModel('hcd/transaction')->saveTransactionData($data);
		} else {
			
			
			/* load order */
			$order = $this->getOrder();
			$order->loadByIncrementId($data['IDENTIFICATION_TRANSACTIONID']);
			if ($order->getPayment() !== false){
				$payment = $order->getPayment()->getMethodInstance();
			}
			$this->log('UniqeID: '.$data['IDENTIFICATION_UNIQUEID']);
			
			
			
			if ($data['PROCESSING_RESULT'] == 'NOK'){
				if (isset($data['FRONTEND_REQUEST_CANCELLED'])){
					$url = $data['FRONTEND_FAILURE_URL'];
				}else {
					$url = $data['FRONTEND_FAILURE_URL'];
				}
				
			} elseif (	( $PaymentCode[1] == 'CP' or	$PaymentCode[1] == 'DB' or $PaymentCode[1] == 'FI' or $PaymentCode[1] == 'RC')
				and	( $data['PROCESSING_RESULT'] == 'ACK' and $data['PROCESSING_STATUS_CODE'] != 80 )) {
				$url = $data['FRONTEND_SUCCESS_URL'];
			}else {
				$url = $data['FRONTEND_SUCCESS_URL'];
			}
			
		Mage::getModel('hcd/transaction')->saveTransactionData($data);
		
		}
		
		$this->log('Url: '.$url);
		
		
		echo $url;
		}
	
	/**
	 * Controller for push notification
	 */
	public function pushAction() {
		
		
		$rawPost = false;
		$lastdata = null;
		$Request = Mage::app()->getRequest();
		$rawPost = $Request->getRawBody();
		
		if ($rawPost === false) {
			$this->_redirect('', array('_secure' => true));
		}
		
		/** Hack to remove a structur problem in criterion node */
		$rawPost = preg_replace('/<Criterion(\s+)name="(.+?)">(.+?)<\/Criterion>/', '<$2>$3</$2>',$rawPost);
		
		$xml = simplexml_load_string($rawPost);
		
		$this->log('XML Object from Push : '.$rawPost);
		
		list($type , $methode) = Mage::helper('hcd/payment')->splitPaymentCode((string)$xml->Transaction->Payment['code']);
		if ( $methode == 'RG') return;
		
		$hash = (string)$xml->Transaction->Analysis->SECRET ;
		$orderID =(string)$xml->Transaction->Identification->TransactionID;
		
		
		
		if (Mage::getModel('hcd/resource_encryption')->validateHash($orderID,$hash) === false) {
			$this->log("Get response form server " . Mage::app()->getRequest()->getServer('REMOTE_ADDR') . " with an invalid hash. This could be some kind of manipulation.", 'WARN');
			$this->_redirect('', array('_forced_secure' => true, '_store_to_url' => true, '_nosid' => true));
			return;
		};
		
		
		
		
		$xmlData = array(
			'PAYMENT_CODE'						=> (string)$xml->Transaction->Payment['code'],
			'IDENTIFICATION_TRANSACTIONID'		=> (string)$orderID,
			'IDENTIFICATION_UNIQUEID'			=> (string)$xml->Transaction->Identification->UniqueID,
			'PROCESSING_RESULT'					=> (string)$xml->Transaction->Processing->Result,
			'IDENTIFICATION_SHORTID'			=> (string)$xml->Transaction->Identification->ShortID,
			'PROCESSING_STATUS_CODE'			=> (string)$xml->Transaction->Processing->Status['code'],
			'PROCESSING_RETURN'					=> (string)$xml->Transaction->Processing->Return,
			'PROCESSING_RETURN_CODE'			=> (string)$xml->Transaction->Processing->Return['code'],
			'PRESENTATION_AMOUNT'				=> (string)$xml->Transaction->Payment->Presentation->Amount,
			'PRESENTATION_CURRENCY'				=> (string)$xml->Transaction->Payment->Presentation->Currency,
			'IDENTIFICATION_REFERENCEID'		=> (string)$xml->Transaction->Identification->ReferenceID,
			'CRITERION_STOREID'					=> (int)$xml->Transaction->Analysis->STOREID,
			'ACCOUNT_BRAND'						=> false,
			'CRITERION_LANGUAGE'				=> strtoupper((string)$xml->Transaction->Analysis->LANGUAGE)
		);
		
		
		
		
		$order = $this->getOrder();
		$order->loadByIncrementId($orderID);
		$paymentCode = $order->getPayment()->getMethodInstance()->getCode();
		
		switch ($paymentCode) {
			
			case 'hcddd':
				$xmlData['CLEARING_AMOUNT'] 			= (string)$xml->Transaction->Payment->Clearing->Amount;
				$xmlData['CLEARING_CURRENCY'] 			= (string)$xml->Transaction->Payment->Clearing->Currency;
				$xmlData['ACCOUNT_IBAN'] 				= (string)$xml->Transaction->Account->Iban;
				$xmlData['ACCOUNT_BIC'] 				= (string)$xml->Transaction->Account->Bic;
				$xmlData['ACCOUNT_IDENTIFICATION'] 		= (string)$xml->Transaction->Account->Identification;
				$xmlData['IDENTIFICATION_CREDITOR_ID'] 	= (string)$xml->Transaction->Identification->CreditorID;
				break;
			case 'hcdbs':
				if ($methode == 'FI') {
					$xmlData['CRITERION_BILLSAFE_LEGALNOTE'] 		= (string)$xml->Transaction->Analysis->BILLSAFE_LEGALNOTE;
					$xmlData['CRITERION_BILLSAFE_AMOUNT'] 			= (string)$xml->Transaction->Analysis->BILLSAFE_AMOUNT;
					$xmlData['CRITERION_BILLSAFE_CURRENCY'] 		= (string)$xml->Transaction->Analysis->BILLSAFE_CURRENCY;
					$xmlData['CRITERION_BILLSAFE_RECIPIENT'] 		= (string)$xml->Transaction->Analysis->BILLSAFE_RECIPIENT;
					$xmlData['CRITERION_BILLSAFE_IBAN'] 			= (string)$xml->Transaction->Analysis->BILLSAFE_IBAN;
					$xmlData['CRITERION_BILLSAFE_BIC'] 				= (string)$xml->Transaction->Analysis->BILLSAFE_BIC;
					$xmlData['CRITERION_BILLSAFE_REFERENCE'] 		= (string)$xml->Transaction->Analysis->BILLSAFE_REFERENCE;
					$xmlData['CRITERION_BILLSAFE_PERIOD'] 			= (string)$xml->Transaction->Analysis->BILLSAFE_PERIOD; 	
					$xmlData['ACCOUNT_BRAND'] 						= 'BILLSAFE';
				}			
				break;
		}
		
		if (!empty($xml->Transaction->Identification->UniqueID))
			$lastdata = Mage::getModel('hcd/transaction')->loadLastTransactionDataByUniqeId($xmlData['IDENTIFICATION_UNIQUEID']);
		
		if($lastdata === false) {
			Mage::getModel('hcd/transaction')->saveTransactionData($xmlData, 'push');	
		}
		
		
		$this->log('PaymentCode '.$paymentCode);
		
		$this->log($type ." ". $methode);
		if ($methode == 'RC' or $methode == 'CP' or	$methode == 'DB' or ($methode == 'FI' and $paymentCode == 'hcdbs' )) {
			Mage::helper('hcd/payment')->mapStatus (
				$xmlData,
				$order
			); 	
			
		}
		
	}
	
		/*
	
	public function testAction() {
		$data = Mage::getModel('hcd/transaction')->loadLastTransactionDataByTransactionnr('302000092');//->loadTransactionDataByX( );
		var_dump($data);	
		foreach($data AS $k) echo "<pre>".print_r($k,1)."</pre>";
	}  
	
	
	
	public function orderAction() {
		$orderID = '302000373';
		$order = $this->getOrder();
		$order->loadByIncrementId($orderID);
		
		if (abs($order->getStore()->roundPrice($order->getTotalPaid()) - $order->getTotalRefunded()) < .0001) {
       		print 'nicht ok';
   		} else
   			print "ok";
	}
	*/
		
}