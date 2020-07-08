<?php

/**
 * Payment Helper
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Helper_BasketApi extends HeidelpayCD_Edition_Helper_AbstractHelper
{
    /**
     * collect items for basket api
     *
     * @param $quote Mage_Sales_Model_Quote|Mage_Sales_Model_Order quote object
     * @param $storeId integer current store id
     * @param $includingShipment boolean include
     *
     * @return array return basket api array
     */
    public function basketItems($quote, $storeId, $includingShipment = false)
    {
        $shoppingCartItems = $quote->getAllVisibleItems();
        $amountGrandTotal = $this->normalizeValue($quote->getGrandTotal());
        $taxAmount = $this->normalizeValue($quote->getTaxAmount());

        $shoppingCart = array(
            'authentication' => array(
                'login' => trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
                'sender' => trim(Mage::getStoreConfig('hcd/settings/security_sender', $storeId)),
                'password' => trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),
            ),
            'basket' => array(
                'amountTotalNet' => $amountGrandTotal - $taxAmount,
                'currencyCode' => $quote->getQuoteCurrencyCode() ?: $quote->getOrderCurrencyCode(),
                'amountTotalDiscount' => $this->normalizeValue(abs($quote->getDiscountAmount())),
                'itemCount' => count($shoppingCartItems),
                'amountTotalVat' => $taxAmount
            )
        );

        $count = 1;
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($shoppingCartItems as $item) {
            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $item->getItemId(),
                'articleId' => $item->getSku(),
                'quantity' => ($item->getQtyOrdered() !== false) ? floor($item->getQtyOrdered()) : $item->getQty(),
                'vat' => floor($item->getTaxPercent()),
                'amountVat' => floor(bcmul($item->getTaxAmount(), 100, 10)),
                'amountGross' => floor(bcmul($item->getRowTotalInclTax(), 100, 10)),
                'amountNet' => floor(bcmul($item->getRowTotal(), 100, 10)),
                'amountPerUnit' => floor(bcmul($item->getRowTotalInclTax(), 100, 10)),
                'amountDiscount' => floor(bcmul($item->getDiscountAmount(), 100, 10)),
                'type' => $item->getIsVirtual() ? 'digital' : 'goods',
                'title' => $item->getName(),
                'imageUrl' => (string)Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail')
            );
            $count++;
        }

        // include shipping as single position
        if ($includingShipment && $this->getShippingInclTax($quote) > 0) {
            // shipment counts as a single position and is also part of the itemCount.
            $shoppingCart['basket']['itemCount']++;

            /** @var Mage_Tax_Model_Calculation $taxCalculation */
            $taxCalculation = Mage::getModel('tax/calculation');
            $taxRateRequest = $taxCalculation->getRateRequest(
                $quote->getShippingAddress(),
                $quote->getBillingAddress(),
                null,
                $quote->getStore()
            );

            /** @var Mage_Tax_Model_Sales_Total_Quote_Shipping $taxRateId */
            $taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $quote->getStore());
            $taxPercent = $taxCalculation->getRate($taxRateRequest->setProductClassId($taxRateId));

            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $count,
                'type' => 'shipment',
                'title' => 'Shipping',
                'quantity' => 1,
                'vat' => $taxPercent,
                'amountVat' => $this->getShippingTaxAmount($quote),
                'amountGross' => $this->getShippingInclTax($quote),
                'amountNet' => $this->getShippingAmount($quote),
                'amountPerUnit' => $this->getShippingInclTax($quote),
                'amountDiscount' => $this->getShippingDiscountAmount($quote)
            );
        }

        return $shoppingCart;
    }

    /**
     * Returns the net shipping cost amount depending on the order/quote type
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $quote
     *
     * @return float
     */
    protected function getShippingAmount($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Quote) {
            return $quote->getShippingAddress()->getShippingAmount();
        }

        /** @var Mage_Sales_Model_Order $quote */
        return $this->normalizeValue($quote->getShippingAmount());
    }

    /**
     * Returns the shipping discount amount depending on the order/quote type
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $quote
     *
     * @return float
     */
    protected function getShippingDiscountAmount($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Quote) {
            return $quote->getShippingAddress()->getShippingDiscountAmount();
        }

        /** @var Mage_Sales_Model_Order $quote */
        return $this->normalizeValue($quote->getShippingDiscountAmount());
    }

    /**
     * Returns the gross shipping cost amount depending on the order/quote type
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $quote
     *
     * @return float
     */
    protected function getShippingInclTax($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Quote) {
            return $quote->getShippingAddress()->getShippingInclTax();
        }

        /** @var Mage_Sales_Model_Order $quote */
        return $this->normalizeValue($quote->getShippingInclTax());
    }

    /**
     * Returns the shipping tax amount depending on the order/quote type
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $quote
     *
     * @return float
     */
    protected function getShippingTaxAmount($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Quote) {
            return $quote->getShippingAddress()->getShippingTaxAmount();
        }

        /** @var Mage_Sales_Model_Order $quote */
        return $this->normalizeValue($quote->getShippingTaxAmount());
    }

    /** Converts an euro amount into cent by multiplying it by hundred.
     *
     * @param int | float $amount
     * @return int
     */
    private function normalizeValue($amount)
    {
        return (int)bcmul($amount, 100, 10);
    }
}
