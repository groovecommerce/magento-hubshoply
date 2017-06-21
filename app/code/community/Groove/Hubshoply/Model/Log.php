<?php

/**
 * HubShop.ly Magento
 * 
 * Log model.
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

class Groove_Hubshoply_Model_Log
    extends Mage_Core_Model_Abstract
{

    /**
     * Local constructor.
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('groove_hubshoply/log');
    }

    /**
     * Pre-save handler.
     *
     * - Sets creation timestamps according to store locale.
     * 
     * @return Groove_Hubshoply_Model_Log
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getCreatedAt()) {
            $this->setCreatedAt(
                Mage::app()->getLocale()
                    ->storeDate(null, null, true, null)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
            );
        }

        if (!is_numeric($this->getStoreId())) {
            $this->setStoreId( (int) Mage::app()->getStore()->getId() );
        }

        return $this;
    }

}