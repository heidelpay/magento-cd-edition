<?php
/**
 * Description only block
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
class HeidelpayCD_Edition_Block_Form_PayolutionInvoice extends Mage_Payment_Block_Form
{
    public $data;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('hcd/form/payolution-invoice.phtml');
    }

    /**
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function fetchInformation()
    {
        /** @var HeidelpayCD_Edition_Model_Payment_Hcdivsan $method */
        $method = $this->getMethod();

        $this->data = $method->getHeidelpayUrl(true);
    }

    /**
     * Returns the GTC-string from the API-response.
     *
     * @return string
     */
    public function getGtcText()
    {
        // GTC-text is inside of CONFIG.OPTIN_TEXT, which is a json object
        if (isset($this->data['CONFIG_OPTIN_TEXT'])) {
            $text =  str_replace(
                array('<p id="payolutiontext">', '</p>', '<a '),
                array('', '', '<a style="margin: 0;float: none;"'),
                $this->data['CONFIG_OPTIN_TEXT']
            );

            return $text;
        }

        return '';
    }
}
