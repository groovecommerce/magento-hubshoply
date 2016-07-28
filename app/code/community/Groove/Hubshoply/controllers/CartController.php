<?php

/**
 * HubShop.ly Magento
 * 
 * Cart controller.
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
 * @category Class_Type_Controller
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

require_once 'Mage/Checkout/controllers/CartController.php';

class Groove_Hubshoply_CartController
    extends Mage_Checkout_CartController
{

    const CART_CONTROL_LOG = 'hubshoply_abdncart_controller.log';

    /**
     * Get current active quote instance
     * 
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return Mage::getSingleton('checkout/cart')->getQuote();
    }

    /**
     * Redirect users to home page, this shouldn't be reachable
     *
     * @return void
     */
    public function indexAction()
    {
        $this->getResponse()->setRedirect(Mage::getUrl(''),301)->sendResponse();
        exit;
    }
    
    /**
     * Restore action.
     * 
     * Add abandoned cart quote to current cart
     * Route: hubshoply/cart/restore/:id
     *
     * - If it's loaded, do nothing
     * - If there is a cart, merge the current and old carts together
     * - If there is no cart, create a new clone of the old abandoned cart
     *
     * @return void
     */
    public function restoreAction()
    {
        //Have home page redirect to fallback to
        $path = Mage::getUrl('');
        //get the Quote ID to restore
        $reqQuoteId = intval($this->getRequest()->getParam('quote'));
        //check if quote ID is a valid positive integer
        if(false && is_int($reqQuoteId) && $reqQuoteId > 0)
        {
            if ( $this->_getQuote()->getId() == $reqQuoteId )
            {
                //if quote is already loaded, redirect to cart
                $path =  Mage::getUrl('checkout/cart');
            }
            else
            {
                //load requested quote
                $quote = Mage::getModel( 'sales/quote' )
                             ->loadByIdWithoutStore( $reqQuoteId );
                //if quote exists, restore/merge it
                if ( $quote->getId() )
                {
                    try{
                        Mage::helper('groove_hubshoply/cart')->restore($this->_getQuote(),$quote);
                    }
                    catch(Exception $x)
                    {
                        Mage::log("Attempted to restore Quote ID $reqQuoteId".PHP_EOL
                                  .$x->getMessage().PHP_EOL.$x->getTraceAsString()
                            ,null,$this::CART_CONTROL_LOG);
                        Mage::getSingleton('core/session')->addError("There was a problem restoring your cart.");
                    }
                    $path = Mage::getUrl('checkout/cart');
                }
                //else the quote does not exist and we move on
            }
        }
        $this->getResponse()->setRedirect($path)->sendResponse();
        exit;
    }
    
}