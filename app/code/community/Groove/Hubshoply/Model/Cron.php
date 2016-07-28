<?php

/**
 * HubShop.ly Magento
 * 
 * Cron model.
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
 * @category Class_Type_Model
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Model_Cron
{

    /**
     * Removes expired auth tokens.
     * 
     * @param Mage_Cron_Model_Schedule $schedule
     *
     * @return void
     */
    public function pruneExpiredTokens(Mage_Cron_Model_Schedule $schedule)
    {
        Mage::helper('groove_hubshoply/cron')->pruneExpiredTokens();
    }

    /**
     * Removes "stale" queue items, similar to log rotation
     * 
     * @param Mage_Cron_Model_Schedule $schedule
     *
     * @return void
     */
    public function pruneStaleQueueitems(Mage_Cron_Model_Schedule $schedule)
    {
        $thisJobCode = $schedule->getJobCode();
        //get what age classifies a queue item as "stale"
        $staleLength = (string)$this->getJobConfig($thisJobCode,'stale_length_in_days');
        //clear "stale" items
        Mage::helper('groove_hubshoply/cron')->pruneStaleQueueItems($staleLength);
    }

    /**
     * Finds abandoned carts to add to queue
     * 
     * @param Mage_Cron_Model_Schedule $schedule
     *
     * @return void
     */
    public function findAbandonCarts(Mage_Cron_Model_Schedule $schedule)
    {
        $thisJobCode = $schedule->getJobCode();
        //all carts older than `$abandonLength` with no order are "abandoned"
        $abandonLength = (string)$this->getJobConfig($thisJobCode,'minutes_until_abandoned');
        Mage::helper('groove_hubshoply/cron')->findAbandonCarts($abandonLength);
    }

    /**
     * Get the job configuration.
     * 
     * @param string $jobCode       Cron job identifier
     * @param null|string $property Name of node containing the property desired
     * 
     * @return SimpleXMLElement[]   The property requested.
     */
    private function getJobConfig($jobCode,$property = null)
    {
        //get all cron jobs
        $allJobs = Mage::getConfig()->getNode('crontab/jobs');
        //get specific job node
        $thisJob = $allJobs->$jobCode;
        return is_null($property)?$thisJob:$thisJob->$property;
    }
    
}