<?php
/**
 * @category	Heidelpay   
 * @package		Heidelpay CD-Editon     
 * @author     	Jens Richter
 * @copyright  	Copyright (c) 2014 Heidelberger Payment GmbH
 */

$installer = $this;
$installer->startSetup();





/**
 * create transactions table
 */
 $tablerealname = 'hcd/transaction';
 $tablename = $installer->getTable($tablerealname);
 if ($installer->getConnection()->isTableExists($tablename) != true) {
 	
 	$table = $installer->getConnection()
 		->newTable($tablename)
 		->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, NULL,
        array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
            'auto_increment' => true
            )
    	)
    	->addColumn('payment_methode', Varien_Db_Ddl_Table::TYPE_VARCHAR, 2,
    	array(
    		'nullable' => false)
    	)
    	->addColumn('payment_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 2,
    	array(
    		'nullable' => false
    		 )
    	)
    	->addColumn('transactionid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50,
    	array(
    		'nullable' => false,
    		'COMMENT' => "normaly the order or basketId"
    		)
    	)
    	->addColumn('uniqeid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32,
    	array(
    		'nullable' => false,
    		'COMMENT'  => "heidelpay uniqe identification number"
    		)
    	)
    	->addColumn('shortid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 14,
    	array(
    		'nullable' => false,
    		'COMMENT'  => "heidelpay sort identification number"
    		)
 		)
 		->addColumn('result', Varien_Db_Ddl_Table::TYPE_VARCHAR, 3,
    	array(
    		'nullable' => false,
    		'COMMENT'  => "heidelpay processing result"
    		)
 		)
 		->addColumn('statuscode',  Varien_Db_Ddl_Table::TYPE_SMALLINT, NULL,
    	array(
    		'unsigned' => true,
            'nullable' => false,
            'COMMENT'  => "heidelpay processing status code"
    		)
 		)
 		->addColumn('return',  Varien_Db_Ddl_Table::TYPE_VARCHAR, 100,
    	array(
    		 'nullable' => false,
            'COMMENT'  => "heidelpay processing return message"
    		)
 		)
 		->addColumn('returncode',  Varien_Db_Ddl_Table::TYPE_VARCHAR, 12,
    	array(
    		 'nullable' => false,
            'COMMENT'  => "heidelpay processing return code"
    		)
 		)
 		->addColumn('jsonresponse',  Varien_Db_Ddl_Table::TYPE_BLOB, NULL,
    	array(
    		 'nullable' => false,
            'COMMENT'  => "heidelpay response as json"
    		)
 		)
 		->addColumn('datetime',  Varien_Db_Ddl_Table::TYPE_TIMESTAMP, NULL,
    	array(
    		 'nullable' => false,
            'COMMENT'  => "create date"
    		)
 		)
 		->addColumn('source',  Varien_Db_Ddl_Table::TYPE_VARCHAR, 100,
    	array(
    		 'nullable' => false,
            'COMMENT'  => "heidelpay processing return message"
    		)
 		)
 		->addIndex($installer->getIdxName($tablerealname, array('uniqeid')), array('uniqeid'))
 		->addIndex($installer->getIdxName($tablerealname, array('transactionid')), array('transactionid'))
 		->addIndex($installer->getIdxName($tablerealname, array('returncode')), array('returncode'))
 		->addIndex($installer->getIdxName($tablerealname, array('source')), array('source'))
 		;
 		$installer->getConnection()->createTable($table);
 }
 
 /**
 * create customer data table
 */
 $tablerealname = 'hcd/customer';
 $tablename = $installer->getTable($tablerealname);
 if ($installer->getConnection()->isTableExists($tablename) != true) {
 	
 	$table = $installer->getConnection()
 		->newTable($tablename)
 		->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, NULL,
        array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
            'auto_increment' => true
            )
    	)
    	->addColumn('paymentmethode', Varien_Db_Ddl_Table::TYPE_VARCHAR, 10,
    	array(
    		'nullable' => false)
    	)
    	->addColumn('uniqeid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50,
    	array(
    		'nullable' => false,
    		'COMMENT' => "Heidelpay transaction identifier"
    		)
    	)
    	->addColumn('customerid', Varien_Db_Ddl_Table::TYPE_INTEGER, NULL,
    	array(
    		'unsigned' => true,
            'nullable' => false,
    		'COMMENT'  => "magento customer id"
    		)
    	)
    	->addColumn('storeid', Varien_Db_Ddl_Table::TYPE_INTEGER, NULL,
    	array(
    		'unsigned' => true,
            'nullable' => false,
    		'COMMENT'  => "magento store id"
    		)
    	)
    	->addColumn('payment_data', Varien_Db_Ddl_Table::TYPE_BLOB, NULL,
    	array(
    		'nullable' => false,
    		'COMMENT'  => "custumer payment data"
    		)
		)
 		->addIndex($installer->getIdxName($tablerealname, array('uniqeid')), array('uniqeid'))
 		->addIndex($installer->getIdxName($tablerealname, array('customerid')), array('customerid'))
 		->addIndex($installer->getIdxName($tablerealname, array('storeid')), array('storeid'))
 		->addIndex($installer->getIdxName($tablerealname, array('paymentmethode')), array('paymentmethode'))
 		;
 		$installer->getConnection()->createTable($table);
 }
 
 

$installer->endSetup();
