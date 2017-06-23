<?php

/**
 * HubShop.ly Magento
 * 
 * Log cron model.
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

class Groove_Hubshoply_Model_Cron_Log
{

    /**
     * Get the log lifetime as an internal datetime string.
     *
     * - Doesn't care about store locale, rough timestamps are fine.
     * 
     * @return string
     */
    private function _getLifetimeDate()
    {
        return Mage::app()->getLocale()
            ->date(null, null, null, false)
            ->subSecond(Groove_Hubshoply_Model_Config::LOG_ENTRY_LIFETIME)
            ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
    }

    /**
     * Clean the log store.
     * 
     * @param Mage_Cron_Model_Schedule $schedule The scheduled task details.
     * 
     * @return 
     */
    public function clean(Mage_Cron_Model_Schedule $schedule)
    {
        $collection = Mage::getResourceModel('groove_hubshoply/log_collection')
            ->addFieldToFilter(
                'created_at',
                array(
                    'lt' => $this->_getLifetimeDate(),
                )
            );

        $count = $collection->getSize();

        $collection->walk('delete');

        return sprintf('Deleted %d records.', $count);
    }

}