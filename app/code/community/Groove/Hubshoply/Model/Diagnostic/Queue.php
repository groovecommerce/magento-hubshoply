<?php

/**
 * Queue diagnostic program.
 * 
 * PHP Version 5
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

class Groove_Hubshoply_Model_Diagnostic_Queue
    implements Groove_Hubshoply_Model_Diagnostic_Interface
{

    const KB_ARTICLE_URL = 'http://support.hubshop.ly/magento/magento-queue-status';

    /**
     * Return dependencies.
     * 
     * @return array
     */
    public function getDependencies()
    {
        return array(
            'enabled' => self::STATUS_PASS,
        );
    }

    /**
     * Determine whether the feature is enabled.
     *
     * @param Varien_Object $object The item to diagnose.
     * 
     * @return void
     */
    public function run(Varien_Object $object)
    {
        $collection = Mage::getResourceModel('groove_hubshoply/queueitem_collection');

        if ( $collection->getSize() > 0 ) {
            $object->setStatus(self::STATUS_PASS)
                ->setDetails(sprintf('Currently %d items in the queue.', $collection->getSize()));
        } else {
            $object->setStatus(self::STATUS_WARN)
                ->setDetails('No items in the queue.')
                ->setUrl(self::KB_ARTICLE_URL);
        }
    }

}