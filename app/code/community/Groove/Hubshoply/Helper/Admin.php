<?php

/**
 * HubShop.ly Magento
 * 
 * Admin helper.
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

class Groove_Hubshoply_Helper_Admin
    extends Mage_Core_Helper_Abstract
{
    
    const HUBSHOPLY_CONSUMER_NAME = 'HubShop.ly';
    const USER_AUTOAUTH_WARNING = "There was an error creating or retrieving your OAuth user credentials. Please contact support.";

    /**
     * Builds the full URL to the Hubshoply App, with query string parameters.
     * 
     * @param string $baseUrl App IFrame URL
     * @param bool   $secure  Whether the store is being used in HTTPS/HTTP mode
     * 
     * @return string
     */
    public function buildUrl($baseUrl, $secure)
    {
        //if front controller is secure, then generate secure URLs
        $secureOption = $secure?array('_secure'=>true):array();
        //get store domain
        $domain = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB,$secure);
        //assemble GET parameters
        $params=array(
            'magento_url'               => $domain,
            'oauth_url'                 => Mage::helper("adminhtml")
                                            ->getUrl("adminhtml/oauth_authorize",$secureOption),
        );
        //try to load OAuth credentials or warn user
        try
        {
            list( $key, $secret ) = $this->loadCredentials();
            $params = $params + array(
                    'magento_consumer_key'    => $key,
                    'magento_consumer_secret' => $secret
                );
        }
        catch(Exception $x)
        {
            Mage::logException($x);
            Mage::getSingleton('adminhtml/session')->addError(
                $this::USER_AUTOAUTH_WARNING
            );
        }
        //build query string
        $queryString = '?'.http_build_query($params);
        //return iFrame URL with proper query string
        return $baseUrl.$queryString;
    }

    /**
     * Will load the OAuth consumer credentials
     * 
     * @return array [Key, Secret] to be used.
     */
    private function loadCredentials()
    {
        $consumer = $this->createOrLoadConsumer();
        return array($consumer->getKey(),$consumer->getSecret());
    }

    /**
     * Creates a Hubshop.ly OAuth Consumer, or returns one if it exists
     * 
     * @param string $name Name of OAuth Consumer
     * 
     * @return Mage_Oauth_Model_Consumer
     */
    private function createOrLoadConsumer($name = Groove_Hubshoply_Helper_Admin::HUBSHOPLY_CONSUMER_NAME)
    {
        $consumer = Mage::getModel('oauth/consumer')->load($name,'name');
        if($consumer->getId())
        {
            return $consumer;
        }
        else
        {
            $helper =  Mage::helper('oauth');
            $consumer = Mage::getModel('oauth/consumer')
                            ->setName($name)
                            ->setKey($helper->generateConsumerKey())
                            ->setSecret($helper->generateConsumerSecret());
            try
            {
                $consumer->save();
                return Mage::getModel('oauth/consumer')
                           ->load( $consumer->getId() );
            }
            catch(Exception $x)
            {
                Mage::logException($x);
                throw $x;
            }
        }
    }

}