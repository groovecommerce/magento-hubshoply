<?php

/**
 * HubShop.ly Magento
 * 
 * Abandoned cart model.
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
 * @category Class_Type_Model
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Model_Abandonedcart
    extends Mage_Core_Model_Abstract
{

    /**
     * Local constructor.
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('groove_hubshoply/abandonedcart');
    }

    /**
     * Loads an abandon cart entry by it's candidate keys (store id and quote id)
     * 
     * @param int $quote_id
     * @param int $store_id
     * 
     * @return Groove_Hubshoply_Model_Abandonedcart
     */
    public function loadByQuoteStore($quote_id, $store_id)
    {
        $id = $this->getCollection()
                      ->addFieldToFilter('quote_id',$quote_id)
                      ->addFieldToFilter('store_id',$store_id)
                      ->getFirstItem()->getId();
        //using $this->load() caused errors, returning another
        // instance of this class with the right data loaded
        return Mage::getModel(__CLASS__)->load($id);
    }

    /**
     * A shortcut to return the quote associated with this Abandoned cart object.
     * 
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getModel('sales/quote')->loadByIdWithoutStore($this->getQuoteId());
    }

    /**
     * Get the payload from the quote.
     * 
     * @return string Return array payload result for queue item
     */
    public function getPayload()
    {
        $cart = $this->getQuote();
        //get visible products in cart
        $product_ids = array();
        foreach ($cart->getAllVisibleItems() as $item) {
            $product_ids[] = intval($item->getProductId());
        }
        //craft the payload
        $payload = array(
            'quote_id'   => intval($cart->getId()),
            'email'      => $cart->getCustomerEmail(),
            'created_at' => date(DateTime::W3C,strtotime($cart->getCreatedAt())),
            'updated_at' => date(DateTime::W3C,strtotime($cart->getUpdatedAt())),
            'total_price' => (string) number_format($cart->getGrandTotal(), 4),
            'product_ids' => $product_ids,
            'qty_in_cart' => $cart->getItemsQty(),
            'currency' => $cart->getQuoteCurrencyCode()
        );
        return $payload;
    }
    
}