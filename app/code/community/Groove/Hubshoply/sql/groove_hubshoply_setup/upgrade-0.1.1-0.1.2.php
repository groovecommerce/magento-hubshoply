<?php

/**
 * HubShop.ly Magento
 * 
 * Upgrade, to create the cart abandonment table.
 * 
 * @category  Setup
 * @package   Groove_Hubshoply
 * @author    Groove Commerce
 * @copyright 2017 Groove Commerce, LLC. All Rights Reserved.
 *
 * LICENSE
 * 
 * The MIT License (MIT)
 * Copyright (c) 2017 Groove Commerce, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
if(!Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_abandonedcart'))
{
    $varchar_type = Varien_Db_Ddl_Table::TYPE_VARCHAR;
    $int_type = Varien_Db_Ddl_Table::TYPE_INTEGER;
    $ts_type = Varien_Db_Ddl_Table::TYPE_TIMESTAMP;
    $ts_init = Varien_Db_Ddl_Table::TIMESTAMP_INIT;
    $non_nullable = array('nullable' => false);
    $connection   = $installer->getConnection();
    $table = $connection->newTable($installer->getTable('hubshoply_abandonedcart') )
                        ->addColumn(
                            'cart_id', $int_type, null,
                            array(
                                'identity' => true,
                                'unsigned' => true,
                                'nullable' => false,
                                'primary' => true,
                                'auto_increment' => true,
                            ),
                            'Cart ID')
                        ->addColumn('quote_id',$int_type, 10, $non_nullable + array('unsigned'=>true), 'Quote ID')
                        ->addColumn('created_at',  $ts_type, null,
                            $non_nullable + array('default' => $ts_init), 'Created At')
                        ->addColumn('updated_at', $ts_type, null, $non_nullable, 'Last Updated At')
                        ->addColumn( 'store_id',  Varien_Db_Ddl_Table::TYPE_SMALLINT, 5, $non_nullable, 'store_id')
    ;
    $table = addForeignKey($table,$installer,'quote_id',$installer->getTable('sales_flat_quote'),'entity_id');
    $table = addForeignKey($table,$installer,'store_id',$installer->getTable('core_store'),'store_id');

    try
    {
        $connection->createTable( $table );
    }
    catch(Exception $x)
    {
        Mage::logException($x);
        throw $x;
    }
}
$installer->endSetup();
/**
 * @param Varien_Db_Ddl_Table $currentTable
 * @param Mage_Core_Model_Resource_Setup $installer
 * @param string $column
 * @param string $linkToTable
 * @param string $linkToColumn
 * @param string $onDelete
 * @param string $onUpdate
 * @return Varien_Db_Ddl_Table
 */
function addForeignKey($currentTable ,$installer,$column, $linkToTable,$linkToColumn, $onDelete = Varien_Db_Ddl_Table::ACTION_CASCADE , $onUpdate = Varien_Db_Ddl_Table::ACTION_CASCADE )
{
    return $currentTable->addForeignKey(
        $installer->getFkName($currentTable->getName(),$column,$linkToColumn,$linkToTable),
        $column, $linkToTable,$linkToColumn,$onDelete,$onUpdate
    );
}