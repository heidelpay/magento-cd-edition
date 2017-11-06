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
        $method->log('SantanderInvoice FormBlock request result: ' . print_r($data, 1));

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
                    $this->advLink,
                    $optinInformation->santander_iv_de_adv_text
                );

                $this->privpolText = str_replace(
                    'hier',
                    $this->privpolLink,
                    $optinInformation->santander_iv_de_privpol_text
                );
            }
        }

        if (!isset($data['CONFIG_OPTIN_TEXT'])) {
            // ... in case the system is not delivering information
            $this->imgLink = 'https://www.santander.de/media/bilder/logos/logos_privatkunden/logo.gif';
            $this->advLink = 'https://www.santander.de/applications/rechnungskauf/werbewiderspruch';
            $this->privpolLink = 'https://www.santander.de/applications/rechnungskauf/datenschutzbestimmungen';

            $this->optin = '';
            $this->privacyPolicy = '';

            $this->advText = 'Ja, ich bin damit einverstanden, dass meine Daten an die Santander Consumer '
                . 'Bank AG („Santander“) weitergegeben werden. Die Santander darf diese Daten gerne dazu nutzen, '
                . 'um mich über Produkte der Santander zu informieren. Natürlich kann ich meine Einwilligung '
                . 'jederzeit mit Wirkung für die Zukunft widerrufen. Ausführliche Informationen zu dieser '
                . 'Einwilligung sowie die Möglichkeit zum Widerruf finde ich '
                . '<a href="' . $this->advLink . '" target="_blank" rel="nofollow noreferrer">hier</a>.';

            $this->privpolText = 'Ich willige in die Übermittlung meiner personenbezogenen Daten an die '
                . 'Santander Consumer Bank AG gemäß den näheren Bestimmungen des beigefügten '
                . '<a href="">Einwilligungserklärungstextes</a> sowie an die darin genannten Auskunftsdateien und in '
                . 'die Durchführung einer automatisierten Entscheidung ein.<br />Nähere Informationen finden Sie in den'
                . ' <a href="'.$this->privpolLink.'" target="_blank" rel="nofollow noreferrer">Datenschutzhinweisen</a>'
                . ' der Santander für den Rechnungs-/Ratenkauf.';
        }
    }
}
