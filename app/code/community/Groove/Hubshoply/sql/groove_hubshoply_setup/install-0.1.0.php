<?php

/**
 * HubShop.ly Magento
 * 
 * Base installation.
 * 
 * @category  Setup
 * @package   Groove_Hubshoply
 * @author    Groove Commerce
 * @copyright 2016 Groove Commerce, LLC. All Rights Reserved.
 *
 * LICENSE
 * 
 * The MIT License (MIT)
 * Copyright (c) 2016 Groove Commerce, LLC.
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
if(!Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_queue'))
{
    $varchar_type = Varien_Db_Ddl_Table::TYPE_VARCHAR;
    $text_type    = Varien_Db_Ddl_Table::TYPE_TEXT;
    $integer_type = Varien_Db_Ddl_Table::TYPE_INTEGER;
    $non_nullable = array('nullable' => false);
    $connection   = $installer->getConnection();
    $table = $connection->newTable( $installer->getTable('hubshoply_queue') )
                        ->addColumn(
                            'queue_id', $integer_type, null,
                            array(
                                'identity' => true,
                                'unsigned' => true,
                                'nullable' => false,
                                'primary' => true,
                                'auto_increment' => true,
                            ),
                            'Entity Id')
                        ->addColumn('event_entity',$varchar_type, 127, $non_nullable, 'Event Entity')
                        ->addColumn('store_id',    $integer_type, null,
                                        array(
                                            'unsigned' => true,
                                            'nullable' => false,
                                        ),
                                    'Store ID')
                        ->addColumn('event_type',  $varchar_type, 127, $non_nullable, 'Event Type')
                        ->addColumn('payload',     $text_type, null, $non_nullable, 'Event JSON Payload')
                        ->addColumn(
                            'created_at',
                            Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
                            null,
                            array(
                                'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
                                'nullable' => false,
                            ),
                            'Event Timestamp')
    ;
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