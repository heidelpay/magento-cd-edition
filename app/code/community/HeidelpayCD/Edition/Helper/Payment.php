<?php
/**
 */
class HeidelpayCD_Edition_Helper_Payment extends Mage_Core_Helper_Abstract
{
	protected $_invoiceOrderEmail = true ;
	protected $_debug 			  = false ;
	
	protected function _getHelper()
		{
		return Mage::helper('hcd');
		}
	
	public function splitPaymentCode($PAYMENT_CODE) {
		return preg_split('/\./' , $PAYMENT_CODE);
		
	}
	
	public function doRequest($url, $params=array())
		{
		$client = new Zend_Http_Client(trim($url), array(
			
		));
		
		if (array_key_exists('raw', $params)) {
			$client->setRawData(json_encode($params['raw']), 'application/json');	
		} else {
		$client->setParameterPost($params);
				}
		if (extension_loaded('curl')) {
			$adapter = new Zend_Http_Client_Adapter_Curl();
			$adapter->setCurlOption(CURLOPT_SSL_VERIFYPEER, true);
			$adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, 2);
			$client->setAdapter($adapter);
		}
		$response = $client->request('POST');
		$res = $response->getBody();
		
		
		if ($response->isError()) {
			
			$this->log("Request fail. Http code : ".$response->getStatus().' Message : '.$res,'ERROR');
			$this->log("Request data : ".print_r($params,1),'ERROR');
			if (array_key_exists('raw', $params)) return $response;
		}
		
		if (array_key_exists('raw', $params)) return json_decode($res,true); 
		
		$result = null;
		parse_str($res, $result);
		
		return $result;
		}
	
	public function preparePostData (	$config 	= array(),
		$frontend 	= array(),
			$userData 	= array(),
				$basketData = array(),
					$criterion = array()) {
		$params = array();
		/*
		 * configurtation part of this function
		 */
		$params['SECURITY.SENDER']	= $config['SECURITY.SENDER'];
		$params['USER.LOGIN'] 		= $config['USER.LOGIN'];
		$params['USER.PWD'] 		= $config['USER.PWD'];
		
		switch ($config['TRANSACTION.MODE']) {
			case 'INTEGRATOR_TEST':	
				$params['TRANSACTION.MODE'] = 'INTEGRATOR_TEST';	
				break;
			case 'CONNECTOR_TEST':	
				$params['TRANSACTION.MODE'] = 'CONNECTOR_TEST';	
				break;
			default:	
				$params['TRANSACTION.MODE'] = 'LIVE';	
		}	
		$params['TRANSACTION.CHANNEL']	= $config['TRANSACTION.CHANNEL'];
		
		
		/* Set payment methode */
		switch ($config['PAYMENT.METHOD']) {
		/* sofortbanking */
			case 'su':
				/* griopay */
			case 'gp':
				/* ideal */
			case 'ide':
				/* eps */
			case 'eps':
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
				$params['PAYMENT.CODE'] = "OT.".$type ;
				break;
				/* postfinace */
			case 'pf':
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
				$params['PAYMENT.CODE'] = "OT.".$type ;
				break;
				/* yapital */
			case 'yt':
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'];
				$params['PAYMENT.CODE'] = "OT.".$type;
				$params['ACCOUNT.BRAND'] 			= "YAPITAL";
				$params['FRONTEND.ENABLED'] 	= "false";
				break;
				/* paypal */
			case 'pal';
			$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'DB' : $config['PAYMENT.TYPE'] ;
			$params['PAYMENT.CODE'] = "VA.".$type ;
			$params['ACCOUNT.BRAND'] = "PAYPAL" ;
			$params['FRONTEND.PM.DEFAULT_DISABLE_ALL'] = "true";
			$params['FRONTEND.PM.0.ENABLED'] = "true";
			$params['FRONTEND.PM.0.METHOD'] = "VA";
			$params['FRONTEND.PM.0.SUBTYPES'] = "PAYPAL" ;
			break;
			/* prepayment */
			case 'pp' :
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
				$params['PAYMENT.CODE'] = "PP.".$type ;
				break;
				/* invoce */
			case 'iv' :
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
				$params['PAYMENT.CODE'] = "IV.".$type ;
				break;
				/* BillSafe */
			case 'bs' :
			$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
			$params['PAYMENT.CODE'] = "IV.".$type ;
			$params['ACCOUNT.BRAND']	= "BILLSAFE";
			$params['FRONTEND.ENABLED']			=	"false";
			break;
			/* BarPay */
			case 'bp' :
			$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
			$params['PAYMENT.CODE'] = "PP.".$type ; 
			$params['ACCOUNT.BRAND'] = "BARPAY";
			$params['FRONTEND.ENABLED']			=	"false";
			break;
			/* MangirKart */
			case 'mk' :
			$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'] ;
			$params['PAYMENT.CODE'] = "PC.".$type ;
			$params['ACCOUNT.BRAND'] = "MANGIRKART";
			$params['FRONTEND.ENABLED']			=	"false";
			break;
			/* MasterPass */
			case 'mpa' :
			$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'DB' : $config['PAYMENT.TYPE'] ;
			
			// masterpass as a payment methode
			if (!array_key_exists('IDENTIFICATION.REFERENCEID',$userData) and( $type == 'DB' or $type == 'PA')) {
						$params['WALLET.DIRECT_PAYMENT'] = "true";
						$params['WALLET.DIRECT_PAYMENT_CODE'] = "WT.".$type ;
						$type = 'IN';
						
			}
			
			$params['PAYMENT.CODE'] 	= "WT.".$type ;
			$params['ACCOUNT.BRAND'] 	= "MASTERPASS";
			break;
			/* default */		 			 		
			default:
				$type = (!array_key_exists('PAYMENT.TYPE',$config)) ? 'PA' : $config['PAYMENT.TYPE'];
				$params['PAYMENT.CODE'] = strtoupper($config['PAYMENT.METHOD']).'.'.$type;  
			break;
		}
		
		/* Debit on registration */
		if(array_key_exists('ACCOUNT.REGISTRATION',$config)) {
			$params['ACCOUNT.REGISTRATION'] = $config['ACCOUNT.REGISTRATION'];
			$params['FRONTEND.ENABLED']		=	"false";
		}
		
		if (array_key_exists('SHOP.TYPE',$config)) $params['SHOP.TYPE'] = $config['SHOP.TYPE'] ;
		if (array_key_exists('SHOPMODUL.VERSION',$config)) $params['SHOPMODUL.VERSION'] = $config['SHOPMODUL.VERSION'] ;
		
		/* frontend configuration */
		
		/* override FRONTEND.ENABLED if nessessary */
		if (array_key_exists('FRONTEND.ENABLED',$frontend)) {
			$params['FRONTEND.ENABLED'] = $frontend['FRONTEND.ENABLED'];
			unset($frontend['FRONTEND.ENABLED']);
		}
		
		if (array_key_exists('FRONTEND.MODE',$frontend)) {
			$params['FRONTEND.MODE'] = $frontend['FRONTEND.MODE'];
			unset($frontend['FRONTEND.MODE']);
		} else {
			$params['FRONTEND.MODE'] = "WHITELABEL";
			$params['TRANSACTION.RESPONSE'] = "SYNC";
			$params['FRONTEND.ENABLED'] = 'true';
		};
		
		
		$params = array_merge($params, $frontend);
		
		/* costumer data configuration */
		$params = array_merge($params, $userData);
		
		/* basket data configuration */
		$params = array_merge($params, $basketData);
		
		/* criterion data configuration */
		$params = array_merge($params, $criterion);
		
		$params['REQUEST.VERSION']			=	"1.0";
		
		return $params ;
	}
	
	
	public function mapStatus ($data ,$order, $message=false) {
		$this->log('mapStatus'.print_r($data,1));
		$PaymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);
		$totalypaid = false ;
		$invoiceMailComment = '';
		
		if (strtoupper($data['CRITERION_LANGUAGE']) == 'DE') {
			$locale = 'de_DE';
			Mage::app()->getLocale()->setLocaleCode($locale);
			Mage::getSingleton('core/translate')->setLocale($locale)->init('frontend', true);
		};
		
		
		$message = (!empty($message))  ? $message : $data['PROCESSING_RETURN'];
		
		$quoteID = ($order->getLastQuoteId() === false) ? $order->getQuoteId() : $order->getLastQuoteId() ; // last_quote_id workaround for trusted shop buyerprotection
		
		if ($data['PROCESSING_RESULT'] == 'NOK'){
			if ($order->canCancel()) {
				$order->cancel();
				
				$order->setState( $order->getPayment()->getMethodInstance()->getStatusError(false),
					$order->getPayment()->getMethodInstance()->getStatusError(true),
					$message );
			}
			
		}	elseif (	( $PaymentCode[1] == 'CP' or	$PaymentCode[1] == 'DB' or $PaymentCode[1] == 'FI' or $PaymentCode[1] == 'RC')
			and	( $data['PROCESSING_RESULT'] == 'ACK' and $data['PROCESSING_STATUS_CODE'] != 80 )) {
			
			/**
			 * Do nothing if status is allready successfull
			 */
			if ($order->getStatus() == $order->getPayment()->getMethodInstance()->getStatusSuccess() ) return ;
			
			
			$message = (isset($data['ACCOUNT_BRAND']) and $data['ACCOUNT_BRAND'] == 'BILLSAFE') ? 'BillSafe Id: '.$data['CRITERION_BILLSAFE_REFERENCE'] : 'Heidelpay ShortID: '.$data['IDENTIFICATION_SHORTID'];
			
			if ($PaymentCode[0] == "IV" or $PaymentCode[0] == "PP") $message = Mage::helper('hcd')->__('recived amount ').$data['PRESENTATION_AMOUNT'].' '.$data['PRESENTATION_CURRENCY'].' '.$message; 
			
			$order->getPayment()->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
			->setParentTransactionId( $order->getPayment()->getLastTransId());
			$order->getPayment()->setIsTransactionClosed(true);
			
			if ( $this->format($order->getGrandTotal()) == $data['PRESENTATION_AMOUNT'] and $order->getOrderCurrencyCode() == $data['PRESENTATION_CURRENCY']) {
				$order->setState( $order->getPayment()->getMethodInstance()->getStatusSuccess(false),
					$order->getPayment()->getMethodInstance()->getStatusSuccess(true),
					$message );
				$totalypaid = true ;
				
			} else {
				/*
				 * in case rc is ack and amount is to low or curreny missmatch
				 */
				$order->setState( $order->getPayment()->getMethodInstance()->getStatusPartlyPaid(false),
					$order->getPayment()->getMethodInstance()->getStatusPartlyPaid(true),
					$message );
			}
			
			$this->log('$totalypaid '.$totalypaid);
			
			$code = $order->getPayment()->getMethodInstance()->getCode();
			
			$path = "payment/".$code."/";
			
			$this->log($path.' Auto invoiced :'.Mage::getStoreConfig($path."invioce", $data['CRITERION_STOREID']).$data['CRITERION_STOREID']);
			
			if ($order->canInvoice() and (Mage::getStoreConfig($path."invioce", $data['CRITERION_STOREID']) == 1 or $code == 'hcdbs') and $totalypaid === true ) {
				$this->log('Can Invoice ? '.($order->canInvoice()) ? 'YES': 'NO');
				$invoice = $order->prepareInvoice();
				$invoice->register()->capture();
				$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
				$invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
				$invoice->setIsPaid(true);
				$order->setIsInProcess(true);
				$order->addStatusHistoryComment(
					Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'),
					false
				);
				$invoice->save();
				if ($this->_invoiceOrderEmail) {
							if ($code != 'hcdpp' and $code != 'hcdiv') {
									$info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
									$invoiceMailComment = ($info === false) ? '' : '<h3>'.$this->__('Payment Information').'</h3>'.$info.'<br/>'; 
							}
							$invoice->sendEmail(true, $invoiceMailComment); // Rechnung versenden
				}
				
				
				
				$transactionSave = Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder());
				$transactionSave->save();
			};
			
			$order->getPayment()->addTransaction(
				Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
				null,
				true,
				$message
			);	
			
			$order->setIsInProcess(true);
		}else {
			if ($order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusSuccess() and $order->getStatus() != $order->getPayment()->getMethodInstance()->getStatusError()) {
				$message = (isset($data['ACCOUNT_BRAND']) and $data['ACCOUNT_BRAND'] == 'BILLSAFE') ? 'BillSafe Id: '.$data['CRITERION_BILLSAFE_REFERENCE'] : 'Heidelpay ShortID: '.$data['IDENTIFICATION_SHORTID'];
				$order->getPayment()->setTransactionId($data['IDENTIFICATION_UNIQUEID']);
				$order->getPayment()->setIsTransactionClosed(0);
				$order->getPayment()->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, null);
				$this->log('Set Transaction to Pending : '.$order->getPayment()->getMethodInstance()->getStatusPendig());
				$order->setState( $order->getPayment()->getMethodInstance()->getStatusPendig(false),
					$order->getPayment()->getMethodInstance()->getStatusPendig(true),
					$message );
				$order->getPayment()->addTransaction(
					Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
					null,
					true,
					$message
				);	
			}
		}
		Mage::dispatchEvent('heidelpay_after_map_status', array('order' => $order));
		$order->save();
		
	}
	
	/**
	 * function to format amount 
	 */
	public function format($number)
		{
		return number_format($number, 2, '.', '');
		}
	
	
	public function getLang($default='en')
		{
		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (!empty($locale) ) {
			return strtoupper($locale[0]);
		}
		return strtoupper($default); //TOBO falses Module
		}
	
	
	/**
	 * helper to generate customer payment error messages
	 */
	public function handleError($errorMsg, $errorCode=null, $ordernr=null)  {
		// default is return generic error message
		if ( $ordernr != null)	$this->log('Ordernumber '.$ordernr.' -> '.$errorMsg.' ['.$errorCode.']','NOTICE');
		
		if ($errorCode) {
			if (!preg_match('/HPError-[0-9]{3}\.[0-9]{3}\.[0-9]{3}/', $this->_getHelper()->__('HPError-'.$errorCode), $matches)) //JUST return when snipet exists
				return $this->_getHelper()->__('HPError-'.$errorCode);
		}
		
		return $this->_getHelper()->__('An unexpected error occurred. Please contact us to get further information.');
	}
	
	/**
	 * anstracted log function because of backtrace
	 */
	
	public function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  $this->realLog( $callers[1]['function'].' '.$message , $level , $file);
	}
	
	/**
	 * real log function which will becalled from all controllers and models
	 */
	
	public function realLog($message, $level="DEBUG", $file=false) {
		$storeId = Mage::app()->getStore()->getId();
		$path = "hcd/settings/";
		$config = array();
		
		
		switch($level) {
			case "CRIT":
				$lev = Zend_Log::CRIT;
				break;
			case "ERR":
			case "ERROR":
				$lev = Zend_Log::ERR ;
				break;
			case "WARN";
			$lev = Zend_Log::WARN;
			break;
			case "NOTICE":
				$lev = Zend_Log::NOTICE;
				break;
			case "INFO":
				$lev = Zend_Log::INFO;
				break;
			default:
				$lev = Zend_Log::DEBUG; 
			if (Mage::getStoreConfig($path."log", $storeId) == 0) return true;
			break; 
		}
		$file = ($file === false) ? "Heidelpay.log" : $file;
		
		Mage::log($message, $lev, $file);		
		return true;
	}
	
	public function basketItems ($quote, $storeId) {
	
	
		$ShoppingCartItems = $quote->getAllVisibleItems();
	
		$ShoppingCart = array(); 
		
		
		        $ShoppingCart = array(
        
        		'authentication' => array(
        		
        						'login'		=> trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
        						'sender' 	=> trim(Mage::getStoreConfig('hcd/settings/security_sender', $storeId)),
        						'password' 	=> trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),
        		
        					),
        
        
        		'basket' =>  array (
        					'amountTotalNet' 		=> floor(bcmul($quote->getGrandTotal(),100,10)),
        					'currencyCode'			=> $quote->getGlobalCurrencyCode(),
        					'amountTotalDiscount'	=> floor(bcmul($quote->getDiscountAmount(),100,10)),
        					'itemCount'				=> count($ShoppingCartItems) 
        					)
        
        
        
        );
        
        $count=1;
        
        foreach ($ShoppingCartItems as $item) {
        	
        	if($this->_debug === true) echo 'Item: '.$count.'<br/><pre>'.print_r($item,1).'</pre>';
        	
        	$ShoppingCart['basket']['basketItems'][] = array(
            										'position' 				=> $count,
            										'basketItemReferenceId' => $item->getItemId(),
            										'unit'					=> 'Stk.',
            										'quantity'				=> ($item->getQtyOrdered()  !== false ) ? floor($item->getQtyOrdered()) : $item->getQty() ,
            										'vat'					=> floor($item->getTaxPercent()),
            										'amountVat'				=> floor(bcmul($item->getTaxAmount(),100,10)),
            										'amountGross'			=> floor(bcmul($item->getRowTotalInclTax(),100,10)),
            										'amountNet'				=> floor(bcmul($item->getRowTotal(),100,10)), 
            										'amountPerUnit'			=> floor(bcmul($item->getPrice(),100,10)),
            										'amountDiscount'		=> floor(bcmul($item->getDiscountAmount(),100,10)),
            										'type'					=> 'goods',
            										'title'					=> $item->getName(),
            										'imageUrl'				=> (string)Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail')
            
            );
            $count++;
        }
        
        if($this->_debug === true) {
        		echo '<pre>'.print_r($ShoppingCart,1).'</pre>';
        		exit();
        }
		return $ShoppingCart;
		
	}
	
	public function getRegion($countryCode, $stateByName) {
		//$regionData = Mage::getModel ( 'directory/region_api' )->items ( $countryCode );
		$regionData = Mage::getModel('directory/region')->getResourceCollection()
                ->addCountryFilter($countryCode)
                ->load();
                
        //$this->log(print_r($regionData,1));
		
		$regionId = null;
		
		foreach ( $regionData as $region ) {
			if (strtolower($stateByName) == strtolower($region ['name']) or $stateByName == $region ['code']) {
				return $region['region_id'];
			}
		}
		// Return last region if mapping fails
		return $region['region_id'] ;
	}
	
}
?>
