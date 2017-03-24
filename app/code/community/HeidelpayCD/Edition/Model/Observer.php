<?php
/**
 * heidelpay observer model
 *
 * @license Use of this software requires acceptance of the License Agreement.
 * @copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
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
class HeidelpayCD_Edition_Model_Observer
{
    public $invoiceOrderEmail = true;

    // @codingStandardsIgnoreLine
    public function removeWalletDataFromCheckout($observer)
    {
        // unset wallet data from session
        if ($session = Mage::getSingleton('checkout/session')) {
            $session->unsHcdWallet();
        }
    }
    
    public function handleWalletDataDuringCheckout($observer)
    {
        $controller = $observer->getControllerAction();
        
        $controllerName = $controller->getRequest()->getControllerName();
        
        $actionName  = $controller->getRequest()->getActionName();

        if (($controllerName == "cart" and $actionName == "index")
            or ($controllerName == "onepage" and $actionName == "index")) {
            
                /**
                 * remove wallet information from session (currently only masterpass)
                 */
                if ($session = Mage::getSingleton('checkout/session')) {
                    $session->unsHcdWallet();
                }
        }
    }
    
    public function saveInvoice($observer)
    {
        // @codingStandardsIgnoreLine should be refactored - issue #4
        $this->log('saveInvoice '.print_r($observer->debug(), 1));
        // @codingStandardsIgnoreLine should be refactored - issue #4
        $this->log('saveInvoice '.print_r($observer->getOrder()->debug(), 1));
    }
    
    public function reportShippingToHeidelpay($observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order    = $shipment->getOrder();
        if (empty($order)) {
            return $this;
        }

        $payment = $order->getPayment()->getMethodInstance();
        
        $paymentCode = $payment->getCode();
        
        
        $paymentMethode = array( 'hcdiv' );
        
        
        
        if (!in_array($paymentCode, $paymentMethode)) {
            return $this;
        } else {
            $path = "payment/".$paymentCode."/";
            if (Mage::getStoreConfig(
                $path."capture_on_delivery",
                $order->getStoreId()
            )) {
                // if invoice on delivery is on try to invoice this order
                $criterion = array();
                /** @var  $authorisation HeidelpayCD_Edition_Model_Transaction */
                $authorisation = Mage::getModel('hcd/transaction')
                    ->getOneTransactionByMethode($order->getRealOrderId(), 'PA');
                
                if ($authorisation === false) {
                    return $this;
                }
                
                
                $config =
                    $payment->getMainConfig($paymentCode, $order->getStoreId());
                $config['PAYMENT.TYPE']        = 'FI';
            
            
                $frontend =
                    $payment->getFrontend(
                        $order->getRealOrderId(),
                        $authorisation['CRITERION_STOREID']
                    );
                $frontend['FRONTEND.MODE']        = 'DEFAULT';
                $frontend['FRONTEND.ENABLED']    = 'false';
            
                $user = $payment->getUser($order, true);
                
                $basketData = $payment->getBasketData($order);
            
                $basketData['IDENTIFICATION.REFERENCEID'] =
                    $authorisation['IDENTIFICATION_UNIQUEID'];
                Mage::dispatchEvent(
                    'heidelpay_reportShippingToHeidelpay_bevor_preparePostData',
                    array(
                        'payment' => $payment,
                        'config' => $config,
                        'frontend' => $frontend,
                        'user' => $user,
                        'basketData' => $basketData,
                        'criterion' => $criterion
                    )
                );
                $params = Mage::helper('hcd/payment')->preparePostData(
                    $config, $frontend, $user, $basketData,
                    $criterion
                );
            
            
            
                $this->log("doRequest url : ".$config['URL']);
                // @codingStandardsIgnoreLine should be refactored - issue #4
                $this->log("doRequest params : ".print_r($params, 1));
            
                $src = Mage::helper('hcd/payment')
                    ->doRequest($config['URL'], $params);
                // @codingStandardsIgnoreLine should be refactored - issue #4
                $this->log("doRequest response : ".print_r($src, 1));

            
            
                if ($src['PROCESSING_RESULT'] == "NOK") {
                    Mage::getSingleton('core/session')
                        ->addError(
                            Mage::helper('hcd')
                                ->__(
                                    'Delivery notes to Heidelpay fail, because of : 
                                    '
                                )
                            .$src['PROCESSING_RETURN']
                        );
                    $shipment->_dataSaveAllowed = false;
                    Mage::app()->getResponse()
                        // @codingStandardsIgnoreLine
                        ->setRedirect($_SERVER['HTTP_REFERER'])
                        ->sendResponse();
                    return;
                } else {
                    Mage::getSingleton('core/session')
                        ->addSuccess(
                            Mage::helper('hcd')
                                ->__('Successfully report delivery to Heidelpay.')
                        );
                }
            }
        }
    }
    
    protected function log($message, $level="DEBUG", $file=false)
    {
        $callers=debug_backtrace();
        return  Mage::helper('hcd/payment')
            ->realLog($callers[1]['function'].' '.$message, $level, $file);
    }
}
