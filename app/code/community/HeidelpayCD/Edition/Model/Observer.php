<?php

/*
 * To change this template, choose Tools | Templates and open the template in the editor.
 */
class HeidelpayCD_Edition_Model_Observer {
	
	var $_invoiceOrderEmail = true;
	
	public function removeWalletDataFromCheckout($observer) {
		// unset wallet data from session
		if($session = Mage::getSingleton('checkout/session')) $session->unsHcdWallet();
		//Mage::log('remove masterpass');
	}
	
	public function handleWalletDataDuringCheckout($observer) {
		
		$controller = $observer->getControllerAction();
		
		$ControllerName = $controller->getRequest()->getControllerName();
		
		$ActionName  = $controller->getRequest()->getActionName();	

		if (($ControllerName == "cart" and $ActionName == "index") or ($ControllerName == "onepage" and $ActionName == "index")) {
			
				/**
				 * remove wallet infomation from session (currently only masterpass)
				 */
				if($session = Mage::getSingleton('checkout/session')) $session->unsHcdWallet();
		} 
		
	}
	
	public function reportShippingToHeidelpay($observer) {
		$shipment = $observer->getEvent()->getShipment();
		$orderIncrementId =  Mage::app()->getRequest()->getParam('order_id');
		Mage::log('$orderIncrementId '.$orderIncrementId);
		$order = Mage::getModel('sales/order');
		$order->loadByAttribute('entity_id', (int)$orderIncrementId);
		
		$this->log('save shipping');
		
		$payment = $order->getPayment()->getMethodInstance();
		
		$paymentCode = $payment->getCode();
		
		
		$PaymentMethode = array ( 'hcdiv' );
		
		
		
		if (!in_array($paymentCode, $PaymentMethode)) {
			return $this;
		} else {
			        	
        	$path = "payment/".$paymentCode."/";
		    if (Mage::getStoreConfig($path."capture_on_delivery",$order->getStoreId())) {
		    	// if invoice on delivery is on try to invoice this order
		    	$criterion = array();
        		$Autorisation = Mage::getModel('hcd/transaction')->getOneTransactionByMethode($order->getRealOrderId() , 'PA');	
        		
        		if( $Autorisation === false ) return $this;
        		
        		
        		$config = $payment->getMainConfig($paymentCode, $order->getStoreId());
				$config['PAYMENT.TYPE']		= 'FI';
			
			
				$frontend = $payment->getFrontend($order->getRealOrderId(), $Autorisation['CRITERION_STOREID']);
				$frontend['FRONTEND.MODE'] 		= 'DEFAULT';
				$frontend['FRONTEND.ENABLED'] 	= 'false';
			
				$user = $payment->getUser($order, true);
				
				$basketData = $payment->getBasketData($order);
			
				$basketData['IDENTIFICATION.REFERENCEID'] = $Autorisation['IDENTIFICATION_UNIQUEID'];
				Mage::dispatchEvent('heidelpay_reportShippingToHeidelpay_bevor_preparePostData', array('payment' => $payment, 'config' => $config, 'frontend' => $frontend, 'user' => $user, 'basketData' => $basketData, 'criterion' => $criterion ));
				$params = Mage::helper('hcd/payment')->preparePostData( $config, $frontend,	$user, $basketData,
				$criterion);
			
			
			
				$this->log("doRequest url : ".$config['URL']);
				$this->log("doRequest params : ".print_r($params,1));
			
				$src = Mage::helper('hcd/payment')->doRequest($config['URL'], $params);
			
				$this->log("doRequest response : ".print_r($src,1));

			
			
			if($src['PROCESSING_RESULT'] == "NOK") {
				Mage::getSingleton('core/session')->addError(Mage::helper('hcd')->__('Delivery notes to Heidelpay fail, because of : ').$src['PROCESSING_RETURN']);
       			$shipment->_dataSaveAllowed = false;
        		Mage::app()->getResponse()->setRedirect($_SERVER['HTTP_REFERER']);
        		Mage::app()->getResponse()->sendResponse();
        		exit();
			} else {
				
				Mage::getSingleton('core/session')->addSuccess(Mage::helper('hcd')->__('Successfully report delivery to Heidelpay.'));
			}
        		
        		
        	
        	
        	};
			
        }
        	
        	
        /*	
        Mage::getSingleton('core/session')->addError('Versand nicht möglich 123');
        $shipment->_dataSaveAllowed = false;
        Mage::app()->getResponse()->setRedirect($_SERVER['HTTP_REFERER']);
        Mage::app()->getResponse()->sendResponse();
        exit();
        */
        
	}
	
	
	private function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  Mage::helper('hcd/payment')->realLog( $callers[1]['function'].' '.$message , $level , $file);
	}
	
}

?>
