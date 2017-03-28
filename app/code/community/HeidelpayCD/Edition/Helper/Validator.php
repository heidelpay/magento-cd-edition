<?php

/**
 * Validator Helper
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
class HeidelpayCD_Edition_Helper_Validator extends HeidelpayCD_Edition_Helper_AbstractHelper
{
    /**
     * Validates the age of the customer
     *
     * It will return true if the costumer is older then 18 years
     *
     * @param $day integer day of the customers birth
     * @param $mount integer month of the customers birth
     * @param $year integer year of the customers birth
     * @param mixed $month
     *
     * @return bool return true if the costumer is older then 18 years
     */
    public function validateDateOfBirth($day, $month, $year)
    {
        // @codingStandardsIgnoreLine should be refactored - issue #2
        if (strtotime("$year/$month/$day") < (time() - (18 * 60 * 60 * 24 * 365))) {
            return true;
        }

        return false;
    }
}
