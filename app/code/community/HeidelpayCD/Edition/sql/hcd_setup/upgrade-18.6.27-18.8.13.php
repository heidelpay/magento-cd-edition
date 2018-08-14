<?php
/**
 * install method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/magento
 *
 * @author  David Owusu
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
/**
 * @var Mage_Core_Model_Resource_Setup $installer
 */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('hcd/customer');
$connection = $installer->getConnection();

// removing all entries from guest customers (storeid = 0)
if ($connection->isTableExists($tableName) === true) {
    $connection->delete($tableName, 'storeid = 0');
}
