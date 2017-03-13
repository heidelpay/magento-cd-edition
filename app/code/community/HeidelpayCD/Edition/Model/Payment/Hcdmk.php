<?php
/**
 *  MagirKart payment method
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
 *
 * @deprecated This payment method is not longer available
 */
class HeidelpayCD_Edition_Model_Payment_Hcdmk extends HeidelpayCD_Edition_Model_Payment_Abstract
{  
	protected $_code = 'hcdmk';
	protected $_canRefund = false;
	protected $_canRefundInvoicePartial = false;
	
	public function isAvailable($quote=null) {
		$currency_code=$this->getQuote()->getQuoteCurrencyCode();
		if (!empty($currency_code) && $currency_code != 'TRY') return false;
		return parent::isAvailable($quote);
	}
	

}

