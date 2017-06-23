<?php

/**
 * Install log table, upgrade consumer.
 * 
 * PHP Version 5
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

/* @var $installer Groove_Hubshoply_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

if (!$this->tableExists('groove_hubshoply/log')) {
    $logTable = $installer->getConnection()
        ->newTable($installer->getTable('groove_hubshoply/log'))
        ->addColumn(
            'log_id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'identity'  => true,
                'unsigned'  => true,
                'nullable'  => false,
                'primary'   => true,
            ),
            'Record entity ID'
        )
        ->addColumn(
            'store_id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => true,
            ),
            'Record store ID'
        )
        ->addColumn(
            'created_at',
            Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
            null,
            array(
                'nullable'  => false,
                'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
            ),
            'Record entry timestamp'
        )
        ->addColumn(
            'level',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => true,
            ),
            'Record log level'
        )
        ->addColumn(
            'message',
            Varien_Db_Ddl_Table::TYPE_TEXT,
            null,
            array(
                'nullable'  => false,
            ),
            'Record data'
        );

    $installer->getConnection()->createTable($logTable);
}

/**
 * Upgrade the consumer.
 */

$storeId    = resolve_store_id();
$consumer   = $this->setupConsumer($storeId);

if ($consumer->getWasUpgraded()) {
    $this->enableFeatures($storeId);
}

$installer->endSetup();

/**
 * Locate the single store likely used in the previous version.
 * 
 * @return integer
 */
function resolve_store_id() {
    $queueItem = Mage::getResourceModel('groove_hubshoply/queueitem_collection')
        ->addFieldToFilter('store_id', array('neq' => 0))
        ->addFieldToFilter('store_id', array('notnull' => true))
        ->setPageSize(1)
        ->getFirstItem();

    if ($queueItem->getStoreId()) {
        return $queueItem->getStoreId();
    }

    return Mage_Core_Model_App::ADMIN_STORE_ID;
}