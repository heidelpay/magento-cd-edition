<?php
/**
 * Onepage progress block
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
class HeidelpayCD_Edition_Block_Onepage_Progress extends Mage_Checkout_Block_Onepage_Progress
{
    public function getBilling()
    {
        Mage::log(' getBillingAddress '.$this->getQuote()->getBillingAddress());
        return $this->getQuote()->getBillingAddress();
    }

    public function getShipping()
    {
        return $this->getQuote()->getShippingAddress();
    }

    public function getShippingMethod()
    {
        return $this->getQuote()->getShippingAddress()->getShippingMethod();
    }

    public function getShippingDescription()
    {
        return $this->getQuote()->getShippingAddress()->getShippingDescription();
    }

    public function getShippingAmount()
    {
        return $this->getQuote()->getShippingAddress()->getShippingAmount();
    }

    public function getPaymentHtml()
    {
        return $this->getChildHtml('payment_info');
    }

    /**
     * Get is step completed.
     * if is set 'toStep' then all steps after him is not completed.
     *
     * @param string $currentStep
     *
     * @see : Mage_Checkout_Block_Onepage_Abstract::_getStepCodes() for allowed values
     *
     * @return bool
     */
    public function isStepComplete($currentStep)
    {
        $autho = Mage::getSingleton('core/session');

        if (!empty(Mage::getSingleton('checkout/session')->getHcdWallet())) {
            Mage::log('isStepComplete yes');
            return true;
        } else {
            $stepsRevertIndex = array_flip($this->_getStepCodes());

            $toStep = $this->getRequest()->getParam('toStep');

            if (empty($toStep) || !isset($stepsRevertIndex[$currentStep])) {
                return $this->getCheckout()->getStepData($currentStep, 'complete');
            }

            if ($stepsRevertIndex[$currentStep] > $stepsRevertIndex[$toStep]) {
                return false;
            }

            return $this->getCheckout()->getStepData($currentStep, 'complete');
        }
    }

    /**
     * Get quote shipping price including tax
     *
     * @return float
     */
    public function getShippingPriceInclTax()
    {
        $inclTax = $this->getQuote()->getShippingAddress()->getShippingInclTax();
        return $this->formatPrice($inclTax);
    }

    public function getShippingPriceExclTax()
    {
        return $this->formatPrice($this->getQuote()->getShippingAddress()->getShippingAmount());
    }

    public function formatPrice($price)
    {
        return $this->getQuote()->getStore()->formatPrice($price);
    }
}
