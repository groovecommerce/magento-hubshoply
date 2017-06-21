<?php

/**
 * HubShop.ly Magento
 * 
 * Cron model.
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

class Groove_Hubshoply_Model_Cron
{

    /* @var $_debug Groove_Hubshoply_Helper_Debug */
    private $_debug;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_debug = Mage::helper('groove_hubshoply/debug');
    }

    /**
     * Removes expired auth tokens.
     * 
     * @param Mage_Cron_Model_Schedule $schedule
     *
     * @return void
     */
    public function pruneExpiredTokens(Mage_Cron_Model_Schedule $schedule)
    {
        $total = Mage::helper('groove_hubshoply/cron')->pruneExpiredTokens();

        $this->_debug->log(sprintf('Pruned %d expired tokens.', $total), Zend_Log::INFO);
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
        $jobCode    = $schedule->getJobCode();
        $length     = (string) $this->getJobConfig($jobCode, 'stale_length_in_days');

        $total = Mage::helper('groove_hubshoply/cron')->pruneStaleQueueItems($length);

        $this->_debug->log(sprintf('Pruned %d stale queue items.', $total), Zend_Log::INFO);
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
        $jobCode    = $schedule->getJobCode();
        $length     = (string)$this->getJobConfig($jobCode, 'minutes_until_abandoned');

        $total = Mage::helper('groove_hubshoply/cron')->findAbandonCarts($length);

        $this->_debug->log(sprintf('Queued %d abandoned carts.', $total), Zend_Log::INFO);
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