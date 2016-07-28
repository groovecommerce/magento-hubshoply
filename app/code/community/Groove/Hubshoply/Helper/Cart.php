<?php

/**
 * HubShop.ly Magento
 * 
 * Cart helper.
 * 
 * @category  Class
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

/**
 * Class declaration
 *
 * @category Class_Type_Helper
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Helper_Cart
    extends Mage_Core_Helper_Abstract
{

    /**
     * Restore a quote.
     * 
     * @param Mage_Sales_Model_Quote $currentQuote Quote from session
     * @param Mage_Sales_Model_Quote $quoteToMergeIn Quote passed into Controller for recovery
     * 
     * @return void
     */
    public function restore($currentQuote, $quoteToMergeIn)
    {
        $isActive = $quoteToMergeIn->getIsActive();
        //reactivate requested quote so it may be merged if necessary
        if(!$isActive)
        {
            $quoteToMergeIn->setIsActive( true );
        }
        //if there is a current quote, merge into it. otherwise load the old one
        if ($currentQuote->getId() )
        {
            //merge request quote into the current session quote
            $this->_mergeAndFinalize($currentQuote,$quoteToMergeIn)->save();
        }
        else
        {
            //create a new active quote for the store
            $newQuote = Mage::getModel('sales/quote')->setIsActive(true)
                            ->setStoreId(Mage::app()->getStore()->getId());
            //merge requested quote into the new quote
            $this->_mergeAndFinalize($newQuote,$quoteToMergeIn)->save();
            //set new quote as the session's quote
            Mage::getSingleton('checkout/cart')->setQuote( $newQuote )->init()->save();
        }
        //deactivate merged quote, new quote will take over
        //this will prevent this from being considered abandoned again.
        $quoteToMergeIn->setIsActive( false );
        //set the cart as updated, due to the merge
        Mage::getSingleton('checkout/session')->setCartWasUpdated( true );
    }

    /**
     * Merge two quotes.
     * 
     * @param Mage_Sales_Model_Quote $newQuote Quote to merge into
     * @param Mage_Sales_Model_Quote $oldQuote Quote to be merged in
     * 
     * @return Mage_Sales_Model_Quote New, fully-merged, quote with totals recalculated.
     */
    private function _mergeAndFinalize($newQuote, $oldQuote)
    {
        return $newQuote->merge($oldQuote)->collectTotals();
    }

}