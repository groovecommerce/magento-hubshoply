<?php

/**
 * HubShop.ly Magento
 * 
 * Token model.
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

class Groove_Hubshoply_Model_Token
    extends Mage_Core_Model_Abstract
{

    const HOUR = 3600; //60*60 seconds
    const DAY  = 86400; //HOUR*24
    const WEEK = 604800; //DAY*7

    /**
     * Local constructor.
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('groove_hubshoply/token');
    }

    /**
     * Determine whether the token is expired.
     * 
     * @return bool Returns if the token is expired
     */
    public function isExpired()
    {
        return strtotime($this->getExpires()) <= time();
    }

    /**
     * Pre-save handler.
     * 
     * @return void
     */
    public function _beforeSave()
    {
        //set to current unix timestamp
        if ( is_null( $this->getData( 'created_at' ) ) )
        {
            $stamp = Mage::getSingleton( 'core/date' )
                ->gmtDate( 'U' );
            $this->setData( 'created_at', $stamp );
        }
        else
        {
            $stamp = strtotime($this->getData( 'created_at' ));
        }

        //set expiration day automatically,
        // or default if user put in a custom datetime
        switch($this->getData('expires'))
        {
            case null:
            case $this::DAY:
                $this->setData('expires', $stamp+$this::DAY );
                break;
            case $this::HOUR:
                $this->setData('expires', $stamp+$this::HOUR );
                break;
            case $this::WEEK:
                $this->setData('expires', $stamp+$this::WEEK );
                break;
            default: break;
        }
        parent::_beforeSave();
    }
    
}