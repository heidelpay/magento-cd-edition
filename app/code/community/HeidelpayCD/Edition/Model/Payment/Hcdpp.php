<?php
class HeidelpayCD_Edition_Model_Payment_Hcdpp extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	protected $_code = 'hcdpp';
	
	
	public function showPaymentInfo($payment_data) {
	
		$load_snippet = $this->_getHelper()->__("Prepayment Info Text");
		
			$repl = array(
					'{AMOUNT}'                    => $payment_data['CLEARING_AMOUNT'],
					'{CURRENCY}'                  => $payment_data['CLEARING_CURRENCY'],
					'{CONNECTOR_ACCOUNT_HOLDER}'  => $payment_data['CONNECTOR_ACCOUNT_HOLDER'],
					'{CONNECTOR_ACCOUNT_IBAN}'    => $payment_data['CONNECTOR_ACCOUNT_IBAN'],
					'{CONNECTOR_ACCOUNT_BIC}'     => $payment_data['CONNECTOR_ACCOUNT_BIC'],
					'{IDENTIFICATION_SHORTID}'    => $payment_data['IDENTIFICATION_SHORTID'],
				);
				
		$load_snippet= strtr( $load_snippet , $repl);
				
		return $load_snippet;
		
		
	}

}

