<?php

/**
 * Direct debit secured payment method
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
class HeidelpayCD_Edition_Model_Payment_HcdDirectDebitSecured extends HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods
{
    /**
     * payment code
     *
     * @var string payment code
     */
    protected $_code = 'hcdddsec';

    /**
     * set checkout form block
     *
     * @var string checkout form block
     */
    protected $_formBlockType = 'hcd/form_directDebitSecured';

    /**
     * over write existing info block
     *
     * @var string
     */
    protected $_infoBlockType = 'hcd/info_directDebit';

    /**
     * Validate customer input on checkout
     *
     * @return $this
     */
    public function validate()
    {
        $this->_postPayload = Mage::app()->getRequest()->getPOST('payment');

        if (isset($this->_postPayload['method']) and $this->_postPayload['method'] == $this->_code) {
            parent::validate();

            if (empty($this->_postPayload[$this->_code . '_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }

            if (empty($this->_postPayload[$this->_code . '_iban'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a iban or account'));
            }

            $this->_validatedParameters['ACCOUNT.HOLDER'] = $this->_postPayload[$this->_code . '_holder'];

            if (preg_match('#^[\d]#', $this->_postPayload[$this->_code . '_iban'])) {
                $this->_validatedParameters['ACCOUNT.NUMBER'] = $this->_postPayload[$this->_code . '_iban'];
            } else {
                $this->_validatedParameters['ACCOUNT.IBAN'] = $this->_postPayload[$this->_code . '_iban'];
            }

            parent::validate();
        }

        return $this;
    }

    /**
     * Payment information for invoice mail
     *
     * @param $paymentData array  transaction response
     *
     * @return string return payment information text
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Direct Debit Info Text");

        $repl = array(
            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
            '{Iban}' => $paymentData['ACCOUNT_IBAN'],
            '{Ident}' => $paymentData['ACCOUNT_IDENTIFICATION'],
            '{CreditorId}' => $paymentData['IDENTIFICATION_CREDITOR_ID'],
        );

        return strtr($loadSnippet, $repl);
    }

    /**
     * @inheritdoc
     */
    public function chargeBack($order, $message = "")
    {
        $message = Mage::helper('hcd')->__('debit failed');
        return parent::chargeBack($order, $message);
    }
}
