<?php

/**
 * Payment Helper
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
class HeidelpayCD_Edition_Helper_BasketApi extends HeidelpayCD_Edition_Helper_AbstractHelper
{
    /**
     * collect items for basket api
     *
     * @param $quote Mage_Sales_Model_Order quote object
     * @param $storeId integer current store id
     * @param $includingShipment boolean include
     *
     * @return array return basket api array
     */
    public function basketItems($quote, $storeId, $includingShipment = false)
    {
        $shoppingCartItems = $quote->getAllVisibleItems();

        $shoppingCart = array(
            'authentication' => array(
                'login' => trim(Mage::getStoreConfig('hcd/settings/user_id', $storeId)),
                'sender' => trim(Mage::getStoreConfig('hcd/settings/security_sender', $storeId)),
                'password' => trim(Mage::getStoreConfig('hcd/settings/user_pwd', $storeId)),
            ),

            'basket' => array(
                'amountTotalNet' => floor(bcmul($quote->getGrandTotal(), 100, 10)),
                'currencyCode' => $quote->getGlobalCurrencyCode(),
                'amountTotalDiscount' => floor(bcmul($quote->getDiscountAmount(), 100, 10)),
                'itemCount' => count($shoppingCartItems)
            )
        );

        $count = 1;
        /** @var  $item Mage_Sales_Model_Order_Item */
        foreach ($shoppingCartItems as $item) {
            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $item->getItemId(),
                'quantity' => ($item->getQtyOrdered() !== false) ? floor($item->getQtyOrdered()) : $item->getQty(),
                'vat' => floor($item->getTaxPercent()),
                'amountVat' => floor(bcmul($item->getTaxAmount(), 100, 10)),
                'amountGross' => floor(bcmul($item->getRowTotalInclTax(), 100, 10)),
                'amountNet' => floor(bcmul($item->getRowTotal(), 100, 10)),
                'amountPerUnit' => floor(bcmul($item->getPrice(), 100, 10)),
                'amountDiscount' => floor(bcmul($item->getDiscountAmount(), 100, 10)),
                'type' => 'goods',
                'title' => $item->getName(),
                'imageUrl' => (string)Mage::helper('catalog/image')->init($item->getProduct(), 'thumbnail')
            );
            $count++;
        }

        if ($includingShipment && $this->getShippingNetPrice($quote) > 0) {
            // shipment counts as a single position and is also part of the itemCount.
            $shoppingCart['basket']['itemCount'] += 1;

            // Shipping amount including tax
            $shippingAmountInclTax = floor(
                bcmul(
                    (($quote->getShippingAmount() - $quote->getShippingRefunded())
                        * (1 + $this->getShippingTaxPercent($quote) / 100)),
                    100, 10
                )
            );

            $this->log('shippingAmountInclTax: ' . $shippingAmountInclTax);

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
            $percent = $taxCalculation->getRate($taxRateRequest->setProductClassId($taxRateId));

            $this->log('Tax Percent: ' . $percent);

            $shoppingCart['basket']['basketItems'][] = array(
                'position' => $count,
                'basketItemReferenceId' => $count,
                'type' => 'shipment',
                'title' => 'Shipping',
                'quantity' => 1,
                'vat' => (int) $this->getShippingTaxPercent($quote),
                'amountVat' => (int) floor(
                    bcmul(
                        ($shippingAmountInclTax - $this->getShippingTaxPercent($quote)),
                        100, 10
                    )
                ),
                'amountGross' => $shippingAmountInclTax,
                'amountNet' => $this->getShippingNetPrice($quote) * 100,
                'amountPerUnit' => $shippingAmountInclTax,
                'amountDiscount' => ''
            );
        }


        return $shoppingCart;
    }

    /**
     * Calculate shipping net price
     *
     * @param $order Mage_Sales_Model_Order magento order object
     *
     * @return string shipping net price
     */
    public function getShippingNetPrice($order)
    {
        $shippingTax = $order->getShippingTaxAmount();
        $price = $order->getShippingInclTax() - $shippingTax;
        $price -= $order->getShippingRefunded();
        $price -= $order->getShippingCanceled();

        $this->log('ShippingNetPrice: ' . $price);
        return $price;
    }

    /**
     * Calculate shipping tax in percent for BillSafe
     *
     * @param $order Mage_Sales_Model_Order magentp order object
     *
     * @return string shipping tex in percent
     */
    public function getShippingTaxPercent($order)
    {
        $tax = ($order->getShippingTaxAmount() * 100) / $order->getShippingAmount();

        $this->log('ShippingTax: ' . $tax);

        return $this->format(round($tax));
    }
}
