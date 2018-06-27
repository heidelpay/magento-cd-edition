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
    public $privacyPolicy;

    /** @var string */
    public $imgLink;

    /** @var string */
    public $advLink;

    /** @var string */
    public $advText;

    /** @var string */
    public $privpolLink;

    /** @var string */
    public $privpolText;

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

                // urls for logo, advanced privacy and policy information
                $this->imgLink = $optinInformation->santander_iv_img_link;
                $this->advLink = $optinInformation->santander_iv_de_adv_link;
                $this->privpolLink = $optinInformation->santander_iv_de_privpol_link;

                // texts for checkboxes (accept policies, ...)
                $this->advText = str_replace(
                    'hier',
                    '<a href="' . $this->advLink . '" target="_blank" rel="nofollow">hier</a>',
                    $optinInformation->santander_iv_de_adv_text
                );

                $this->privpolText = str_replace(
                    'Einwilligungserklärungstextes',
                    '<a href="' . $this->privpolLink . '" target="_blank" rel="nofollow">hier</a>',
                    $optinInformation->santander_iv_de_privpol_text
                );
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
    public function getAdvLink()
    {
        return $this->advLink;
    }

    /**
     * @return string
     */
    public function getAdvText()
    {
        return $this->advText;
    }

    /**
     * @return string
     */
    public function getPrivpolLink()
    {
        return $this->privpolLink;
    }

    /**
     * @return string
     */
    public function getPrivpolText()
    {
        return $this->privpolText;
    }
}
