<?php
class HeidelpayCD_Edition_Model_Order_Pdf_Invoice extends Mage_Sales_Model_Order_Pdf_Invoice
{
	
    public function getPdf($invoices = array())
    {
    	Mage::log('Invoice'.print_r($invoices,1));
    	
    	// return $this->myPdf($invoices);
    }
    
    public function myPdf($invoices = array())
    {
    	$debug = false;
    	if ($debug){
        $this->_beforeGetPdf();
        $this->_initRenderer('invoice');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);

        $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
        $pdf->pages[] = $page;
        //$page->setFillColor(new Zend_Pdf_Color_RGB(1, 0, 0));
        $this->_setFontRegular($page);
        //$page->drawText('Dies ist ein Test', 35, 780, 'UTF-8');
        
				$x = 50;
				$y = 800;
    	}
			foreach ($invoices as $invoice) {
				$order = $invoice->getOrder();
				$billing = $order->getBillingAddress();
	    	$payment = $order->getPayment()->getMethodInstance();
	    	
	    	// Immer in der Basisw�hrung des Shops abrechnen
		    //$amount		= number_format($this->getOrder()->getBaseGrandTotal(), 2, '.', '');
		    //$currency	= $this->getOrder()->getBaseCurrencyCode();
		    // in der aktuell ausgew�hlten W�hrung abrechnen
		    $amount		= number_format($order->getGrandTotal(), 2, '.', '');
		    $currency	= $order->getOrderCurrencyCode();
	    	
	    	$street		= $billing->getStreet();
    		$locale   = explode('_', Mage::app()->getLocale()->getLocaleCode());
	    	if (is_array($locale) && ! empty($locale))
		      $language = $locale[0];
		    else
		      $language = $this->getDefaultLocale();
	    	
	    	$userId  = $order->getCustomerId();
		    $orderId  = $payment->getTransactionId();
		    $insertId = $orderId;
		    $orderId .= '-'.$userId;
		    $payCode = 'IV';
		    $payMethod = 'FI';
		
		    $userData = array(
		      'firstname' => $billing->getFirstname(),
		      'lastname'  => $billing->getLastname(),
		      'salutation'=> 'MR',#($order->customer['gender']=='f' ? 'MRS' : 'MR'),
		      'street'    => $street[0],
		      'zip'       => $billing->getPostcode(),
		      'city'      => $billing->getCity(),
		      'country'   => $billing->getCountry(),
		      'email'     => $order->getCustomerEmail(),
		      'ip'        => $order->getRemoteIp(),
		    );
				if (empty($userData['ip'])) $userData['ip'] = $_SERVER['REMOTE_ADDR']; // Falls IP Leer, dann aus dem Server holen
	    	// Payment Request zusammenschrauben
		    $data = $payment->prepareData($orderId, $amount, $currency, $payCode, $userData, $language, $payMethod, true);
	    	$bsParams = $payment->getBillsafeBasket($order);
	    	$data = array_merge($data, $bsParams);
	    	$data['IDENTIFICATION.REFERENCEID'] = $order->getPayment()->getLastTransId();
	    	if ($debug){
        	foreach ($data AS $k => $v){
						$page->drawText($k.': '.$v, $x, $y, 'UTF-8');
						$y-= 10;	
					}
	    	}				
		    // Mit Payment kommunizieren
		    $res = $payment->doRequest($data);
		    //if ($debug) echo '<pre>resp('.print_r($this->response, 1).')</pre>';
		    //if ($debug) echo '<pre>'.print_r($res, 1).'</pre>';
		    // Payment Antwort auswerten
		    $res = $payment->parseResult($res);
		    //if ($debug) echo '<pre>'.print_r($res, 1).'</pre>';
		    if ($debug) $page->drawText(print_r($res,1), $x, $y, 'UTF-8');
			}
			if ($debug){
        $this->_afterGetPdf();
        return $pdf;
			}
			return parent::getPdf($invoices);
    }
    public function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  Mage::helper('hcd/payment')->realLog( $callers[1]['function'].' '. $message , $level , $file);
	}
};