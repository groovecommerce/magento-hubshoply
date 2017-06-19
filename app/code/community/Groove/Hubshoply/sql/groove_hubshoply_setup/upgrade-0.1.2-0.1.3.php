<?php

/**
 * HubShop.ly Magento
 * 
 * Upgrade, to modify token, queue tables; establish constraints.
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
try
{
    //Update Hubshoply token to have a foreign key to the Oauth consumer
    if(Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_token'))
    {
        $hs_token    = $installer->getTable( 'hubshoply_token' );
        $mg_consumer = $installer->getTable( 'oauth_consumer' );
        $connection  = $installer->getConnection();
        //Consumer_id in our table column needs to match Magento's Oauth entity_id column definition
        $connection->changeColumn($hs_token,'consumer_id','consumer_id', array(
                'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
                'length' => 10,
                'nullable' => false,
                'unsigned' => true,
            )
        );
        //add foreign key between columns
        $table       = $connection->addForeignKey(
            $installer->getFkName( $hs_token, 'consumer_id', $mg_consumer,  'entity_id'),
            $hs_token, 'consumer_id',
            $mg_consumer, 'entity_id'
        );
    }
    //Update Queue to have a foreign key to stores
    if(Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_queue'))
    {
        $hs_queue    = $installer->getTable( 'hubshoply_queue' );
        $mg_store = $installer->getTable( 'core_store' );
        $connection  = $installer->getConnection();
        //match store_id definitions across tables
        $connection->changeColumn($hs_queue,'store_id','store_id', array(
                'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
                'length' => 5,
                'nullable' => false,
            )
        );
        //add foreign key
        $table       = $connection->addForeignKey(
            $installer->getFkName( $hs_queue, 'store_id', $mg_store, 'store_id' ),
            $hs_queue, 'store_id',
            $mg_store, 'store_id'
        );
    }

}
catch(Exception $x)
{
    Mage::logException($x);
    throw $x;
}
$installer->endSetup();