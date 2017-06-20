<?php

/**
 * HubShop.ly Magento
 * 
 * Admin helper.
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
 * @category Class_Type_Helper
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Helper_Oauth
    extends Mage_Core_Helper_Abstract
{

    /**
     * Construct a HubShop.ly communication URL.
     *
     * Output paramater notes:
     * 
     *  - magento_url => The storefront URL for primary API access
     *  - oauth_url   => The callback URL to the admin controller for OAuth initiation
     * 
     * @param string                    $url     Target URL to extend.
     * @param bool                      $secure  Optional secure context flag.
     * @param integer                   $storeId Optional store ID for URL generation context.
     * @param Mage_Oauth_Model_Consumer $storeId Optional specified consumer instance.
     * 
     * @return string
     */
    public function buildUrl($url, $secure = null, $storeId = null, Mage_Oauth_Model_Consumer $consumer = null)
    {
        if (is_null($secure)) {
            $secure = Mage::app()->getStore()->isCurrentlySecure();
        }

        if ($storeId === true) {
            $storeId = Mage::app()->getStore(Mage::app()->getRequest()->getParam('store'))->getId();
        }

        if (!$consumer) {
            $consumer = $this->getConsumer();
        }

        $params = array(
            'magento_consumer_key'      => $consumer->getKey(),
            'magento_consumer_secret'   => $consumer->getSecret(),
            'magento_url'               => Mage::getSingleton('groove_hubshoply/config')
                ->getFrontendUrl(null, array(), $storeId),
            'oauth_url'                 => Mage::getSingleton('groove_hubshoply/config')
                ->getAdminUrl('adminhtml/oauth_authorize', array('_secure', $secure), $storeId),
        );
        
        return preg_replace('/\?*/', '', $url) . '?' . http_build_query($params);
    }

    /**
     * Get or generate the designated consumer model.
     * 
     * @param string  $name         The consumer name.
     * @param boolean $autoGenerate Optional flag to autogenerate if needed.
     * 
     * @return Mage_Oauth_Model_Consumer
     */
    public function getConsumer($name = null, $autoGenerate = true)
    {
        if (is_null($name)) {
            $name = Groove_Hubshoply_Model_Config::OAUTH_CONSUMER;
        }

        $consumer = Mage::getModel('oauth/consumer')->load($name, 'name');

        if ($consumer->getId()) {
            return $consumer;
        } else if ($autoGenerate) {
            $helper     = Mage::helper('oauth');
            $consumer   = Mage::getModel('oauth/consumer')
                ->setName($name)
                ->setCallbackUrl(Groove_Hubshoply_Model_Config::REMOTE_AUTH_URI)
                ->setKey($helper->generateConsumerKey())
                ->setSecret($helper->generateConsumerSecret());

            try {
                $consumer->save();
            } catch (Mage_Core_Exception $error) {
                throw $error;
            } catch(Exception $error) {
                Mage::logException($error);
            }
        }

        return $consumer;
    }

}