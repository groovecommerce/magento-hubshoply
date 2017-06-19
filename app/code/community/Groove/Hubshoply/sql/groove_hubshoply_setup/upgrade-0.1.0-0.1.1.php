<?php

/**
 * HubShop.ly Magento
 * 
 * Upgrade, to create the token table.
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
if(!Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_token'))
{
    $varchar_type = Varien_Db_Ddl_Table::TYPE_VARCHAR;
    $integer_type = Varien_Db_Ddl_Table::TYPE_INTEGER;
    $timestamp_type = Varien_Db_Ddl_Table::TYPE_TIMESTAMP;
    $non_nullable = array('nullable' => false);
    $connection   = $installer->getConnection();
    $table = $connection->newTable( $installer->getTable('hubshoply_token') )
                        ->addColumn(
                            'token_id', $integer_type, null,
                            array(
                                'identity' => true,
                                'unsigned' => true,
                                'nullable' => false,
                                'primary' => true,
                                'auto_increment' => true,
                            ),
                            'Token Id')
                        ->addColumn('consumer_id',$varchar_type, 127, $non_nullable, 'Consumer ID')
                        ->addColumn('token',  $varchar_type, 127, $non_nullable, 'Token')
                        ->addColumn('created_at', $timestamp_type, null,
                            array(
                                'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
                                'nullable' => false,
                            ), 'Event Timestamp')
                        ->addColumn( 'expires', $timestamp_type, null, $non_nullable, 'Event Timestamp')
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