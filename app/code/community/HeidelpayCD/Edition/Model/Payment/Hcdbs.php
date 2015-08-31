<?php
class HeidelpayCD_Edition_Model_Payment_Hcdbs extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	protected $_code = 'hcdbs';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;

	
	public function isAvailable($quote=null) {
		$billing  = $this->getQuote()->getBillingAddress();
		$shipping  = $this->getQuote()->getShippingAddress();
		
		if (($billing->getFirstname() 	!= $shipping->getFirstname()) 	or 
				($billing->getLastname() 	!= $shipping->getLastname()) 	or
				($billing->getStreet() 		!= $shipping->getStreet())		or			
				($billing->getPostcode() 	!= $shipping->getPostcode())	or
				($billing->getCity() 			!= $shipping->getCity())	or
				($billing->getCountry() 		!= $shipping->getCountry())) {
			
			return false;		
		}
		return parent::isAvailable($quote);
	}
	
	public function showPaymentInfo($payment_data) {
	
		$load_snippet = $this->_getHelper()->__("BillSafe Info Text");
		
			$repl = array(
					'{LEGALNOTE}'                 => $payment_data['CRITERION_BILLSAFE_LEGALNOTE'],
					'{AMOUNT}'                    => $payment_data['CRITERION_BILLSAFE_AMOUNT'],
					'{CURRENCY}'                  => $payment_data['CRITERION_BILLSAFE_CURRENCY'],
					'{CONNECTOR_ACCOUNT_HOLDER}'  => $payment_data['CRITERION_BILLSAFE_RECIPIENT'],
					'{CONNECTOR_ACCOUNT_IBAN}'    => $payment_data['CRITERION_BILLSAFE_IBAN'],
					'{CONNECTOR_ACCOUNT_BIC}'     => $payment_data['CRITERION_BILLSAFE_BIC'],
					'{IDENTIFICATION_SHORTID}'    => $payment_data['CRITERION_BILLSAFE_REFERENCE'],
					'{PERIOD}'					  => $payment_data['CRITERION_BILLSAFE_PERIOD']
				);
				
		$load_snippet= strtr( $load_snippet , $repl);
				
			
		return  $load_snippet;
		
	}
}	