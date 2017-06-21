<?php

/**
 * HubShop.ly Magento
 * 
 * Cron helper.
 * 
 * @category  Class
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

/**
 * Class declaration
 *
 * @category Class_Type_Helper
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Helper_Cron
    extends Mage_Core_Helper_Abstract
{

    /**
     * This function will delete all expired authentication tokens.
     *
     * @return integer
     */
    public function pruneExpiredTokens()
    {
        //get all tokens with an additional column
        // telling how many days they have until they expire
        $tokens = Mage::getModel('groove_hubshoply/token')
                      ->getCollection()
                      ->addExpressionFieldToSelect(
                          'time_diff_token_expirey',
                          'TIMESTAMPDIFF(MINUTE,NOW(),{{exp}})',
                          array('exp' => 'expires')
                      );
        //select those who are past expiration
        $tokens->getSelect()->having('time_diff_token_expirey <= 0');
        //delete them
        
        $total = $tokens->getSize();

        $tokens->walk('delete');

        return $total;
    }

    /**
     * Anything equal to or greater than $days old will be pruned.
     * 
     * @param int $days The age limit for queue items.
     *
     * @return integer
     */
    public function pruneStaleQueueItems($days)
    {
        //get all queue items with an additional column for age
        $queueitems = Mage::getModel('groove_hubshoply/queueitem')
                          ->getCollection()
                          ->addExpressionFieldToSelect(
                              'date_diff_queue_age',
                              'DATEDIFF(NOW(),{{cat}})',
                              array('cat' => 'created_at')
                          );
        //filter to get all items older than `$days` days
        $queueitems->getSelect()->having('date_diff_queue_age >= '.$days);
        //delete them
        
        $total = $queueitems->getSize();

        $queueitems->walk('delete');

        return $total;
    }

    /**
     * Anything equal to or greater than $minutes old without checkout will be flagged abandoned.
     * 
     * @param int $minutes The age an order-less cart/quote is considered abandoned
     *
     * @return integer
     */
    public function findAbandonCarts($minutes)
    {
        //get read connection and update index
        $adapter = Mage::getSingleton('core/resource')->getConnection('sales_read');
        //get SQL fragment for finding carts that haven't been updated in the last `$minutes` minutes
        $from = $adapter->getDateSubSql(
            $adapter->quote(now()),
            $minutes,
            Varien_Db_Adapter_Interface::INTERVAL_MINUTE
        );
        //get quotes collection, never converted. Updated before `$minutes` minutes ago
        $abandoned = Mage::getResourceModel('sales/quote_collection')
                      ->addFieldToFilter('converted_at', array( //this is supposed to be the determining factor
                          array('eq'=>$adapter->getSuggestedZeroDate()),
                          array('null'=>null),
                      ))
                      ->addFieldToFilter('customer_email', array('notnull' => ''))
                      ->addFieldToFilter('is_active', 1) //this is the determining factor
                      ->addFieldToFilter('updated_at', array('to' => $from));
        //get abandoned ID's
        $abandonedIds = $abandoned->getColumnValues(Mage::getModel('sales/quote')->getIdFieldName());
        //All "abandoned carts" that have since been ordered can be removed from the abandoned cart index
        $converted = Mage::getModel('groove_hubshoply/abandonedcart')->getCollection()
                        ->addFieldToFilter('quote_id',array('nin'=>$abandonedIds))->walk('delete');

        $total = count($abandonedIds);

        //Add or update Abandoned cart index
        $abandoned->walk(array($this,'trackAbandonCart'));

        return $total;
    }

    /**
     * Takes an unconverted quote from Magento, and stores it in an Abandoned Cart index
     * 
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    public function trackAbandonCart(Mage_Sales_Model_Quote $quote)
    {
        //get abandoned cart for quote, or a new one if it doesn't exist
        $cart = Mage::getModel('groove_hubshoply/abandonedcart')
            ->loadByQuoteStore($quote->getId(),$quote->getStoreId());
        //if it doesn't exist, set the creation stamp, quote, and store id.
        if(!$cart->getId())
        {
            $cart
                ->setCreatedAt($quote->getCreatedAt())
                ->setQuoteId($quote->getId())
                ->setStoreId($quote->getStoreId());
        }
        //existing carts only need to know if there is an updated timestamp
        if($cart->getUpdatedAt() != $quote->getUpdatedAt())
        {
            //if the timestamp is updated, it becomes a new item to queue
            $cart
                ->setUpdatedAt($quote->getUpdatedAt())
                ->setEnqueued(false);
        }
        try{
            $cart->save();
        }
        catch(Exception $x){
            Mage::logException($x);
        }
    }
    
}