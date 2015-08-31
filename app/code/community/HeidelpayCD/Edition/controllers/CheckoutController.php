<?php

/*
 * Wallet Express Checkout
 */
 
$module_path = Mage::getModuleDir('', 'Mage_Checkout');
require_once ($module_path."/controllers/OnepageController.php");

class HeidelpayCD_Edition_CheckoutController extends Mage_Checkout_OnepageController {
	
	public function indexAction() {
		
		$session = Mage::getSingleton('checkout/session');
		$checkout = $this->getOnepage();
		$quote = $session->getQuote();
		
		if (!$quote->hasItems() || $quote->getHasError()) {
			$this->_redirect('checkout/cart');
			return;
		}
		
		
		$data = false;
		$this->loadLayout();		
		$this->getLayout()->getBlock('head')->setTitle($this->__('MasterPass Checkout'));   
		
		$session->setCurrency($quote->getGlobalCurrencyCode());
		$session->setTotalamount($quote->getGrandTotal());
		
		$data = Mage::getModel('hcd/transaction')->loadLastTransactionDataByTransactionnr(Mage::getSingleton('checkout/session')->getQuoteId());
		
		Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
		Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_secure' => true)));
		$this->getOnepage()->initCheckout();
		
		
		$this->log('Data from wallet '.print_r($data,1));
		
		
		
		$this->getOnepage()->saveCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
		
		$this->getOnepage()->getCheckout()->setStepData('login', 'complete', true);
		
		$region = Mage::helper('hcd/payment')->getRegion($data['ADDRESS_COUNTRY'],$data['ADDRESS_STATE']);
		
		$billingAddress = Array(
			"address_id" => '',
			"firstname" => trim($data['NAME_GIVEN']),
			"lastname" => trim($data['NAME_FAMILY']),
			"company" => '',
			"email" => trim($data['CONTACT_EMAIL']),
			"street" => array(
				"0" => $data['ADDRESS_STREET'],
				"1" => '',
			),
			"region_id" =>(string) $region,
			"region" => $data['ADDRESS_STATE'],
			"city" => $data['ADDRESS_CITY'],
			"postcode" =>  $data['ADDRESS_ZIP'],
			"country_id" => $data['ADDRESS_COUNTRY'],
			"telephone" => preg_replace('/[A-z]-/', '',$data['CONTACT_PHONE']),
			"customer_password" =>"",
			"confirm_password" =>"",
			"save_in_address_book" => 1
		);
		
		$this->log('adress'.print_r($billingAddress,1));
		
		$hpdata = array(
			'code' 			=> 'hcdmpa',
			'brand'			=> (array_key_exists('ACCOUNT_BRAND', $data)) ? $data['ACCOUNT_BRAND'] : false ,
			'mail'			=> (array_key_exists('CONTACT_EMAIL', $data)) ? $data['CONTACT_EMAIL'] : false ,
			'number'		=> (array_key_exists('ACCOUNT_NUMBER', $data)) ? $data['ACCOUNT_NUMBER'] : false ,
			'expiryMonth'	=> (array_key_exists('ACCOUNT_EXPIRY_MONTH', $data)) ? $data['ACCOUNT_EXPIRY_MONTH'] : false ,
			'expiryYear'	=> (array_key_exists('ACCOUNT_EXPIRY_YEAR', $data)) ? $data['ACCOUNT_EXPIRY_YEAR'] : false ,
			'referenceId'	=> (array_key_exists('IDENTIFICATION_UNIQUEID', $data)) ? $data['IDENTIFICATION_UNIQUEID'] : false ,
			'adress'		=> $billingAddress
		);
		
		$session->setHcdWallet($hpdata);        
		
		
		$customAddress = Mage::getModel('customer/address');
		$customAddress->setData($billingAddress);
		
		$quote->setBillingAddress(Mage::getSingleton('sales/quote_address')
			->importCustomerAddress($customAddress));
		
		$quote->setShippingAddress(Mage::getSingleton('sales/quote_address')
			->importCustomerAddress($customAddress));
		
		$this->getOnepage()->getQuote()->collectTotals()->save();
		
		
		$this->getOnepage()->getCheckout()->setStepData('billing', 'complete', true);
		$this->getOnepage()->getCheckout()->setStepData('shipping', 'allow', true);
		
		$this->getOnepage()->getCheckout()->setStepData('shipping', 'complete', true);
		$this->getOnepage()->getCheckout()->setStepData('payment', 'allow', true);
		$this->getOnepage()->savePayment(array('method'=>'hcdmpa'));
		
		
		
		$this->renderLayout();
		
	}
	
	public function saveBillingAction()
		{
		if ($this->_expireAjax()) {
			return;
		}
		if ($this->getRequest()->isPost()) {
			
			$data = $this->getRequest()->getPost('billing', array());
			$customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
			
			if (isset($data['email'])) {
				$data['email'] = trim($data['email']);
			}
			$result = $this->getOnepage()->saveBilling($data, $customerAddressId);
					
			
			if (!isset($result['error'])) {
				if ($this->getOnepage()->getQuote()->isVirtual()) {
				    $result['goto_section'] = 'payment';
                    $result['update_section'] = array(
                        'name' => 'payment-method',
                        'html' => $this->_getPaymentMethodsHtml()
                    );

				} else {
					
					$wallet = Mage::getSingleton('checkout/session')->getHcdWallet();
					
					$result = $this->getOnepage()->saveShipping($wallet['adress'], false);
					$this->getOnepage()->getCheckout()->setStepData('shipping', 'allow', true)
													  ->setStepData('shipping', 'complete', true);
					
					if (!isset($result['error'])) {
						$result['goto_section'] = 'shipping_method';
						$result['update_section'] = array(
							'name' => 'shipping-method',
							'html' => $this->_getShippingMethodsHtml()
						);
					}
				}
			}
			
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		}
		}
	
	public function savePayment($data)
		{
		try {
			$result = $this->getOnepage()->savePayment($data);
			
			
			$redirectUrl = $this->getOnepage()->getQuote()->getPayment()->getCheckoutRedirectUrl();
			if (empty($result['error']) && !$redirectUrl) {
				$this->loadLayout('checkout_onepage_review');
				$result['goto_section'] = 'review';
				$result['update_section'] = array(
					'name' => 'review',
					'html' => $this->_getReviewHtml()
				);
			}
			if ($redirectUrl) {
				$result['redirect'] = $redirectUrl;
			}
		} catch (Mage_Payment_Exception $e) {
			if ($e->getFields()) {
				$result['fields'] = $e->getFields();
			}
			$result['error'] = $e->getMessage();
		} catch (Mage_Core_Exception $e) {
			$result['error'] = $e->getMessage();
		} catch (Exception $e) {
			Mage::logException($e);
			$result['error'] = $this->__('Unable to set Payment Method.');
		}
		$this->getOnepage()->getCheckout()->setStepData('payment', 'complete', true);
		return $result ;
	}
	
	
	public function savePaymentAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
            $result = $this->savePayment(array('method'=>'hcdmpa'));

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
	
	protected function _getPaymentMethodsHtml()
    {
        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        $update->load('hcd_checkout_paymentmethod');
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        return $output;
    }
    
    
       public function saveShippingMethodAction()
    {
        if ($this->_expireAjax()) {
            return;
        }
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = $this->getOnepage()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty
            if (!$result) {
                Mage::dispatchEvent(
                    'checkout_controller_onepage_save_shipping_method',
                     array(
                          'request' => $this->getRequest(),
                          'quote'   => $this->getOnepage()->getQuote()));
                $this->getOnepage()->getQuote()->collectTotals();
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

                $result['goto_section'] = 'payment';
                $result['update_section'] = array(
                    'name' => 'payment-method',
                    'html' => $this->_getPaymentMethodsHtml()
                );
            }
            $this->getOnepage()->getQuote()->collectTotals()->save();
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }
    
    
    public function progressAction()
    {
        // previous step should never be null. We always start with billing and go forward
        $prevStep = $this->getRequest()->getParam('prevStep', false);

        if ($this->_expireAjax() || !$prevStep) {
            return null;
        }

        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        /* Load the block belonging to the current step*/
        $update->load('hcd_checkout_progress_' . $prevStep);
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        $this->getResponse()->setBody($output);
        return $output;
    }
    
	
	private function log($message, $level="DEBUG", $file=false) {
		$callers=debug_backtrace();
		return  Mage::helper('hcd/payment')->realLog( $callers[1]['function'].' '.$message , $level , $file);
	}
	
}
