<?php

/**
 * Invoice by Santander form Block class
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2017-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento-cd-edition
 *
 * @author Stephano Vogel
 *
 * @package heidelpay/magento-cd-edition/block/form/santander-invoice
 */
class HeidelpayCD_Edition_Block_Form_SantanderInvoice extends Mage_Payment_Block_Form
{
    /** @var string */
    public $optin;

    /** @var string */
    public $imgLink;

    /** @var string */
    public $privacyPolicy;

    /**
     * Replaces paragraph-tag (<p>) with span-tag (<span>).
     *
     * @param $text
     * @return mixed
     */
    public function stripParagraphTag($text)
    {
        return str_replace('<p', '<span', $text);
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('hcd/form/santander-invoice.phtml');
    }

    /**
     * Sets Formblock information (urls, logo image url, ...) about Santander Invoice.
     */
    public function setSantanderInformation()
    {
        /** @var HeidelpayCD_Edition_Model_Payment_Hcdivsan $method */
        $method = $this->getMethod();

        $data = $method->getHeidelpayUrl(true);

        // Santander Information is inside of CONFIG.OPTIN_TEXT, which is a json object
        if (isset($data['CONFIG_OPTIN_TEXT'])) {
            /** @var stdClass $optinInformation */
            $optinInformation = json_decode($data['CONFIG_OPTIN_TEXT']);

            if ($optinInformation !== null) {
                // optin and privacy policy information texts
                $this->optin = $optinInformation->optin;
                $this->privacyPolicy = $optinInformation->privacy_policy;

                // url for the santander logo
                $this->imgLink = $optinInformation->logolink;
            }
        }
    }

    /**
     * @return string
     */
    public function getOptin()
    {
        return $this->optin;
    }

    /**
     * @return string
     */
    public function getOptinText()
    {
        return $this->stripParagraphTag($this->getOptin());
    }

    /**
     * @return string
     */
    public function getPrivacyPolicy()
    {
        return $this->privacyPolicy;
    }

    /**
     * @return string
     */
    public function getImgLink()
    {
        return $this->imgLink;
    }

    /**
     * @return string
     */
    public function getPrivpolText()
    {
        return $this->stripParagraphTag($this->getPrivacyPolicy());
    }
}
