<?php
/** @noinspection LongInheritanceChainInspection */
/**
 * Abstract payment method
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
class HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * validation helper
     *
     * @var HeidelpayCD_Edition_Helper_Validator $_validatorHelper
     */
    protected $_validatorHelper;

    /**
     * validated parameter
     *
     * @var array validated parameter
     */
    protected $_validatedParameters = array();

    /**
     * post data from checkout
     *
     * @var array $_postPayload post data from checkout
     */
    protected $_postPayload = array();

    /**
     * This payment method allows business to business
     *
     * @var bool
     */
    protected $_allowsBusinessToBusiness = false;

    /**
     * Controls whether an insurance denial should be stored to make the payment method unavailable.
     *
     * @var bool
     */
    protected $_remembersInsuranceDenial = true;

    /**
     * HeidelpayCD_Edition_Model_Payment_AbstractSecuredPaymentMethods constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_infoBlockType = 'hcd/info_invoice';
        $this->_formBlockType = 'hcd/form_invoiceSecured';

        $this->_validatorHelper = Mage::helper('hcd/validator');
    }

    /**
     * @inheritdoc
     *
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function isAvailable($quote = null)
    {
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

        /* payment method is b2c only */
        if (!$this->allowsBusinessToBusiness() && !empty($billing->getCompany())) {
            return false;
        }

        // prohibit payment method if the customer has already been rejected in the current session
        $hasCustomerBeenRejected = 'get' . $this->getCode() . 'CustomerRejected';
        if ($this->remembersInsuranceDenial() && $this->getCheckout()->$hasCustomerBeenRejected()) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Validate customer input on checkout
     *
     * @return $this
     *
     * @throws \Mage_Core_Exception
     */
    public function validate()
    {
        parent::validate();

        if (isset($this->_postPayload['method']) && $this->_postPayload['method'] === $this->getCode()) {
            if (array_key_exists($this->getCode() . '_salutation', $this->_postPayload)) {
                $this->_validatedParameters['NAME.SALUTATION'] =
                    (
                        $this->_postPayload[$this->getCode() . '_salutation'] === 'MR' ||
                        $this->_postPayload[$this->getCode() . '_salutation'] === 'MRS'
                    )
                        ? $this->_postPayload[$this->getCode() . '_salutation'] : '';
            }

            if (array_key_exists($this->getCode() . '_dobday', $this->_postPayload) &&
                array_key_exists($this->getCode() . '_dobmonth', $this->_postPayload) &&
                array_key_exists($this->getCode() . '_dobyear', $this->_postPayload)
            ) {
                $day = (int)$this->_postPayload[$this->getCode() . '_dobday'];
                $month = (int)$this->_postPayload[$this->getCode() . '_dobmonth'];
                $year = (int)$this->_postPayload[$this->getCode() . '_dobyear'];

                if ($this->_validatorHelper->validateDateOfBirth($day, $month, $year)) {
                    $this->_validatedParameters['NAME.BIRTHDATE']
                        = $year . '-' . sprintf('%02d', $month) . '-' . sprintf('%02d', $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment method.')
                    );
                }
            }

            $this->saveCustomerData($this->_validatedParameters);
        }

        return $this;
    }

    /**
     * Payment information for invoice mail
     *
     * @param array $paymentData transaction response
     *
     * @return string return payment information text
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__('Invoice Info Text');

        $replace = array(
            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
            '{CONNECTOR_ACCOUNT_HOLDER}' => $paymentData['CONNECTOR_ACCOUNT_HOLDER'],
            '{CONNECTOR_ACCOUNT_IBAN}' => $paymentData['CONNECTOR_ACCOUNT_IBAN'],
            '{CONNECTOR_ACCOUNT_BIC}' => $paymentData['CONNECTOR_ACCOUNT_BIC'],
            '{IDENTIFICATION_SHORTID}' => array_key_exists('CONNECTOR_ACCOUNT_USAGE', $paymentData) ?
                $paymentData['CONNECTOR_ACCOUNT_USAGE'] :
                $paymentData['IDENTIFICATION_SHORTID']
        );

        return strtr($loadSnippet, $replace);
    }

    /**
     * Handle transaction with means pending
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     *
     * @throws \Mage_Core_Exception
     */
    public function pendingTransaction($order, $data, $message = '')
    {
        $message = 'Heidelpay ShortID: ' . $data['IDENTIFICATION_SHORTID'] . ' ' . $message;

        /** @noinspection PhpUndefinedMethodInspection */
        $order->getPayment()
            ->setTransactionId($data['IDENTIFICATION_UNIQUEID'])
            ->setParentTransactionId($order->getPayment()->getLastTransId())
            ->setIsTransactionClosed(false);

        /** @var Mage_Sales_Model_Service_Order $salesOrder */
        $salesOrder = Mage::getModel('sales/service_order', $order);

        /** @var Mage_Sales_Model_Convert_Order $convertOrder */
        $convertOrder = Mage::getModel('hcd/convert_order');
        $invoice = $salesOrder->setConvertor($convertOrder)->prepareInvoice();
        $invoice->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN);

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setIsInProcess(true);

        /** @noinspection PhpUndefinedMethodInspection */
        $invoice->setIsPaid(false);
        $order->addStatusHistoryComment(Mage::helper('hcd')->__('Automatically invoiced by Heidelpay.'));
        $invoice->save();

        // send invoice email if payment method is configured to do so
        if ($this->canInvoiceOrderEmail() && $this->isSendingInvoiceAutomatically($data)) {
            $invoiceMailComment = '';
            if ($this->isSendingInvoiceMailComment()) {
                /** @noinspection PhpUndefinedMethodInspection */
                $info = $order->getPayment()->getMethodInstance()->showPaymentInfo($data);
                $invoiceMailComment = ($info === false) ? '' : '<h3>'
                    . $this->_getHelper()->__('payment information') . '</h3><p>' . $info . '</p>';
            }

            $this->log('Sending invoice email for order #' . $order->getRealOrderId() . '...');
            $invoice->sendEmail(true, $invoiceMailComment);
        }


        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionSave->save();

        $this->log('Setting order status/state to processed and generate invoice.');

        /** @noinspection PhpUndefinedMethodInspection */
        $order->setState(
            $order->getPayment()->getMethodInstance()->getStatusSuccess(),
            $order->getPayment()->getMethodInstance()->getStatusSuccess(true)
        );

        $order->getPayment()->addTransaction(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            null,
            true,
            $message
        );

        return $order;
    }

    /**
     * Handle transaction with means processing
     *
     * @param $order Mage_Sales_Model_Order
     * @param $data HeidelpayCD_Edition_Model_Transaction
     * @param $message string order history message
     *
     * @return Mage_Sales_Model_Order
     *
     * @throws \Mage_Core_Exception
     */
    public function processingTransaction($order, $data, $message = '')
    {
        /** @var HeidelpayCD_Edition_Helper_InvoiceHelper $invoiceHelper */
        $invoiceHelper = Mage::helper('hcd/InvoiceHelper');
        return $invoiceHelper->handleInvoicePayment($order, $data, $message);
    }

    /**
     * Returns true if the payment method supports business to business.
     *
     * @return bool
     */
    public function allowsBusinessToBusiness()
    {
        return $this->_allowsBusinessToBusiness;
    }

    /**
     * Returns true if an insurance denial should be stored to make the payment method unavailable.
     *
     * @return bool
     */
    public function remembersInsuranceDenial()
    {
        return $this->_remembersInsuranceDenial;
    }
}
