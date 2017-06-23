<?php

/**
 * HubShop.ly Magento
 * 
 * Cart controller.
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
 * @category Class_Type_Controller
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'CartController.php';

class Groove_Hubshoply_CartController
    extends Mage_Checkout_CartController
{

    /**
     * Get the current active quote.
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
        $this->getResponse()
            ->setRedirect(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), 301)
            ->sendResponse();

        exit;
    }

    /**
     * Restore abandoned cart action.
     *
     * @return void
     */
    public function restoreAction()
    {
        $path           = Mage::getUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $activeQuote    = $this->_getQuote();
        $targetQuoteId  = intval($this->getRequest()->getParam('quote'));

        if ( is_int($targetQuoteId) && $targetQuoteId > 0 ) {
            if ($activeQuote->getId() == $targetQuoteId) {
                $path = Mage::getUrl('checkout/cart');
            } else {
                $targetQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($targetQuoteId);

                if ( $targetQuote->getId() > 0 ) {
                    try {
                        Mage::helper('groove_hubshoply/cart')->restore($activeQuote, $targetQuote);
                    } catch (Exception $error) {
                        Mage::helper('groove_hubshoply/debug')->logError($error);

                        Mage::getSingleton('core/session')->addError(
                            $this->__('There was a problem restoring your cart.')
                        );
                    }

                    $path = Mage::getUrl('checkout/cart');
                }
            }
        }

        $this->getResponse()
            ->setRedirect($path)
            ->sendResponse();

        exit;
    }

}