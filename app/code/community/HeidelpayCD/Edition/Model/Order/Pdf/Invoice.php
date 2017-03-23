<?php
/**
 * Order pdf invoice
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
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
class HeidelpayCD_Edition_Model_Order_Pdf_Invoice extends Mage_Sales_Model_Order_Pdf_Invoice
{
    public function getPdf($invoices = array())
    {
        // @codingStandardsIgnoreLine refactored - issue #4
        Mage::log('Invoice'.print_r($invoices, 1));

    }
    
    public function myPdf($invoices = array())
    {
        $debug = false;
        if ($debug) {
            $this->_beforeGetPdf();
            $this->_initRenderer('invoice');

            $pdf = new Zend_Pdf();
            $this->_setPdf($pdf);
            $style = new Zend_Pdf_Style();
            $this->_setFontBold($style, 10);

            $page = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
            $pdf->pages[] = $page;

        $this->_setFontRegular($page);

        
                $x = 50;
            $y = 800;
        }

        foreach ($invoices as $invoice) {
            $order = $invoice->getOrder();
            $billing = $order->getBillingAddress();
            $payment = $order->getPayment()->getMethodInstance();
            
            $amount        = number_format($order->getGrandTotal(), 2, '.', '');
            $currency    = $order->getOrderCurrencyCode();
            
            $street        = $billing->getStreet();
            $locale   = explode('_', Mage::app()->getLocale()->getLocaleCode());
            if (is_array($locale) && ! empty($locale)) {
                $language = $locale[0];
            } else {
                $language = $this->getDefaultLocale();
            }
            
            $userId  = $order->getCustomerId();
            $orderId  = $payment->getTransactionId();
            $insertId = $orderId;
            $orderId .= '-'.$userId;
            $payCode = 'IV';
            $payMethod = 'FI';
        
            $userData = array(
              'firstname' => $billing->getFirstname(),
              'lastname'  => $billing->getLastname(),
              'salutation'=> 'MR',
              'street'    => $street[0],
              'zip'       => $billing->getPostcode(),
              'city'      => $billing->getCity(),
              'country'   => $billing->getCountry(),
              'email'     => $order->getCustomerEmail(),
              'ip'        => $order->getRemoteIp(),
            );
            if (empty($userData['ip'])) {
                // @codingStandardsIgnoreLine
                $userData['ip'] = $_SERVER['REMOTE_ADDR'];
            } // Falls IP Leer, dann aus dem Server holen
            // Payment Request zusammenschrauben
            $data = $payment
                ->prepareData($orderId, $amount, $currency, $payCode, $userData, $language, $payMethod, true);
            $bsParams = $payment->getBillsafeBasket($order);
            $data = array_merge($data, $bsParams);
            $data['IDENTIFICATION.REFERENCEID'] = $order->getPayment()->getLastTransId();
            if ($debug) {
                foreach ($data as $k => $v) {
                    $page->drawText($k.': '.$v, $x, $y, 'UTF-8');
                    $y-= 10;
                }
            }


            $res = $payment->doRequest($data);

            $res = $payment->parseResult($res);
        }

        if ($debug) {
            $this->_afterGetPdf();
            return $pdf;
        }

        return parent::getPdf($invoices);
    }

    public function log($message, $level="DEBUG", $file=false)
    {
        $callers=debug_backtrace();
        return  Mage::helper('hcd/payment')->realLog($callers[1]['function'].' '. $message, $level, $file);
    }
}
