<?php
/**
 * Invoice unsecured payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Simon Gabriel
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_Hcdivpol extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdivpol';

    /**
     * @var string checkout information and form
     */
    protected $_formBlockType = 'hcd/form_invoicePayolution';

    public function isAvailable($quote = null)
    {
        // check shipping address is same as before
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        /* billing and shipping address has to match */
        if (($billing->getFirstname() !== $shipping->getFirstname()) ||
            ($billing->getLastname() !== $shipping->getLastname()) ||
            ($billing->getStreet() !== $shipping->getStreet()) ||
            ($billing->getPostcode() !== $shipping->getPostcode()) ||
            ($billing->getCity() !== $shipping->getCity()) ||
            ($billing->getCountry() !== $shipping->getCountry())
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Validate input data from checkout
     *
     * @return HeidelpayCD_Edition_Model_Payment_Abstract
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        if (isset($this->_postPayload['method']) && $this->_postPayload['method'] === $this->_code) {
            if (array_key_exists($this->_code . '_salutation', $this->_postPayload)) {
                $this->_validatedParameters['NAME.SALUTATION'] =
                    (
                        $this->_postPayload[$this->_code . '_salutation'] === 'MR' or
                        $this->_postPayload[$this->_code . '_salutation'] === 'MRS'
                    )
                        ? $this->_postPayload[$this->_code . '_salutation'] : '';
            }

            if (array_key_exists($this->_code . '_dobday', $this->_postPayload) &&
                array_key_exists($this->_code . '_dobmonth', $this->_postPayload) &&
                array_key_exists($this->_code . '_dobyear', $this->_postPayload)
            ) {
                $day = (int)$this->_postPayload[$this->_code . '_dobday'];
                $month = (int)$this->_postPayload[$this->_code . '_dobmonth'];
                $year = (int)$this->_postPayload[$this->_code . '_dobyear'];

                if ($this->_validatorHelper->validateDateOfBirth($day, $month, $year)) {
                    $this->_validatedParameters['NAME.BIRTHDATE']
                        = $year . '-' . sprintf('%02d', $month) . '-' . sprintf('%02d', $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment methode.')
                    );
                }
            }
        }


        parent::validate();

        $this->saveCustomerData($this->_validatedParameters);

        return $this;
    }
}
