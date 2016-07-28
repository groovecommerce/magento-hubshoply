<?php

/**
 * HubShop.ly Magento
 * 
 * Base module helper.
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
 
class Groove_Hubshoply_Helper_Data
    extends Mage_Core_Helper_Abstract
{

    /**
     * Timing attack String comparison, case sensitive
     * 
     * @param string $knownString String to compare against (hash/password/unknown)
     * @param string $userString String provided by user (input/potentially unsafe)
     * 
     * @return bool Returns true if they are the same
     */
    public function safeCompare($knownString, $userString)
    {
        //XOR the strings together
        $res = $knownString ^ $userString;
        //will automatically be "false" if the strings are different sizes
        // ($ret will be 0 if they are the same length)
        $ret = strlen($knownString) ^ strlen($userString);
        //check for differences, all characters should be '0'
        // any differences will add to $ret
        for($i = strlen($userString) - 1; $i >= 0; $i--)
            { $ret += ord($res[$i]); }
        //if they are the same, $ret == 0, !$ret is true. Else it is false for any differences
        return !$ret;
    }

    /**
     * Used in the Setup to check for database table existence
     * 
     * @param Mage_Core_Model_Resource_Setup $installer
     * @param string $tableName
     * 
     * @return boolean
     */
    function checkTableExists($installer, $tableName)
    {
        return $installer->tableExists(
            $installer->getTable($tableName)
        );
    }
    
}