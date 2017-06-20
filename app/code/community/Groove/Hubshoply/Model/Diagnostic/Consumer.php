<?php

/**
 * OAuth consumer diagnostic program.
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

class Groove_Hubshoply_Model_Diagnostic_Consumer
    implements Groove_Hubshoply_Model_Diagnostic_Interface
{

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
     * Determine whether the consumer is configured.
     *
     * @param Varien_Object $object The item to diagnose.
     * 
     * @return void
     */
    public function run(Varien_Object $object)
    {
        $consumer = Mage::helper('groove_hubshoply/oauth')->getConsumer(
            null,
            false,
            Mage::app()->getStore()->getId()
        );

        $successful = $consumer->getId() > 0;

        $object->setStatus( $successful ? self::STATUS_PASS : self::STATUS_FAIL );

        if (!$successful) {
            $object->setDetails('OAuth consumer is not available.');
        }
    }

}