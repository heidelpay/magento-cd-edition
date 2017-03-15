<?php
/**
 * Invoice secured payment method
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
class HeidelpayCD_Edition_Model_Payment_HcdInvoiceSecured
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdivsec';

    protected $_formBlockType = 'hcd/form_invoiceDebitSecured';

    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');


        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code.'_salut', $payment)) {
                $params['NAME.SALUTATION'] =
                    (preg_match('/[A-z]{2}/', $payment[$this->_code.'_salut']))
                        ? $payment[$this->_code.'_salut'] : '';
            }

            if (array_key_exists($this->_code.'_dobday', $payment) &&
                array_key_exists($this->_code.'_dobmonth', $payment) &&
                array_key_exists($this->_code.'_dobyear', $payment)
            ) {
                $day    = (int)$payment[$this->_code.'_dobday'];
                $mounth = (int)$payment[$this->_code.'_dobmonth'];
                $year    = (int)$payment[$this->_code.'_dobyear'];

                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] = $year.'-'.sprintf("%02d", $mounth).'-'.sprintf("%02d", $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment methode.')
                    );
                }
            }


            $this->saveCustomerData($params);
        }

        return $this;
    }
}
