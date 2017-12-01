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
        $tokens = Mage::getResourceModel('groove_hubshoply/token_collection');

        $tokens->getSelect()
            ->columns(array('time_diff_token_expirey' => new Zend_Db_Expr('TIMESTAMPDIFF(MINUTE, NOW(), expires)')))
            ->having('time_diff_token_expirey <= 0');

        $total = count($tokens);

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
     * Anything equal to or greater than $abandonAge old without checkout will be flagged abandoned.
     * 
     * @param int $abandonAge The age an order-less cart/quote is considered abandoned
     *
     * @return integer
     */
    public function findAbandonCarts($abandonAge = null)
    {
        $userConfig = Mage::getSingleton('groove_hubshoply/config');

        if (!$abandonAge || $userConfig->getMinutesUntilAbandoned()) {
            $abandonAge = (int) $userConfig->getMinutesUntilAbandoned();
        }

        $adapter    = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $upperBound = $adapter->getDateSubSql($adapter->quote(now()), $abandonAge, Varien_Db_Adapter_Interface::INTERVAL_MINUTE);
        $lowerBound = $adapter->getDateSubSql($adapter->quote(now()), $userConfig->getMaxCartAgeDays(), Varien_Db_Adapter_Interface::INTERVAL_DAY);
        $collection = Mage::getResourceModel('sales/quote_collection')
            ->addFieldToFilter(
                'converted_at',
                array(
                    array('eq' => $adapter->getSuggestedZeroDate()),
                    array('null' => null),
                )
            )
            ->addFieldToFilter(
                'updated_at',
                array(
                    'from'  => $lowerBound,
                    'to'    => $upperBound
                )
            )
            ->addFieldToFilter('customer_email', array('notnull' => ''))
            ->addFieldToFilter('is_active', 1);

        $select = $collection->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array('id' => 'entity_id', 'store_id', 'created_at', 'updated_at'));

        $statement  = $adapter->query($select);
        $object     = new Varien_Object();
        $quoteIds   = array();

        while ( ($row = $statement->fetch()) ) {
            call_user_func(array($this, 'trackAbandonCart'), $object->setData($row));

            $quoteIds[] = $row['id'];
        }

        // @todo consider process deferment or other refactor
        Mage::getResourceModel('groove_hubshoply/abandonedcart_collection')
            ->addFieldToFilter('quote_id', array('nin' => $quoteIds))
            ->walk('delete');

        return count($quoteIds);
    }

    /**
     * Takes an unconverted quote from Magento, and stores it in an Abandoned Cart index
     * 
     * @param Varien_Object $quote
     *
     * @return void
     */
    public function trackAbandonCart(Varien_Object $quote)
    {
        if ( !Mage::getSingleton('groove_hubshoply/config')->isEnabled($quote->getStoreId()) ) {
            return;
        }

        $cart = Mage::getModel('groove_hubshoply/abandonedcart')
            ->loadByQuoteStore($quote->getId(), $quote->getStoreId());

        if (!$cart->getId()) {
            $cart->setCreatedAt($quote->getCreatedAt())
                ->setQuoteId($quote->getId())
                ->setStoreId($quote->getStoreId());
        }

        if ($cart->getUpdatedAt() != $quote->getUpdatedAt()) {
            $cart->setUpdatedAt($quote->getUpdatedAt())
                ->setEnqueued(false);
        }

        try {
            $cart->save();
        } catch (Exception $error) {
            Mage::logException($error);
        }
    }
    
}