<?php

/**
 * HubShop.ly Magento
 * 
 * Upgrade, to add constraints.
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
try
{
    //Update Hubshoply token to have a foreign key to the Oauth consumer
    if(Mage::helper('groove_hubshoply')->checkTableExists($installer,'hubshoply_abandonedcart'))
    {
        $hs_carts    = $installer->getTable( 'hubshoply_abandonedcart' );
        $columns = array('quote_id','store_id');
        $constraint = Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE;
        $connection  = $installer->getConnection();
        //Add unique contraint
        $connection->addIndex($hs_carts,
            $installer->getIdxName($hs_carts,$columns,$constraint),
            $columns, $constraint);
    }
}
catch(Exception $x)
{
    Mage::logException($x);
    throw $x;
}
$installer->endSetup();