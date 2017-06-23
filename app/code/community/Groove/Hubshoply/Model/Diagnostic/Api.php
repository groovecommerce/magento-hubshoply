<?php

/**
 * API test diagnostic program.
 *
 * - Simulates token request authorization & provides exchange
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

class Groove_Hubshoply_Model_Diagnostic_Api
    implements Groove_Hubshoply_Model_Diagnostic_Interface
{

    // For development testing only -- live service will not regard this setting
    const VERIFY_PEER = false;

    /**
     * Attempt to verify redirect rules for API access.
     *
     * - Expects Apache RewriteRule in stock Magento .htaccess file
     * - Expects nginx rewrite directive in most common locations
     * 
     * @return boolean
     */
    private function _checkRedirectRule()
    {
        $server         = (string) Mage::app()->getRequest()->getServer('SERVER_SOFTWARE');
        $expectedString = null;
        $contents       = null;

        if (preg_match('/apache/im', $server)) {
            $expectedString = '~^[\s\r\n]*[^#]*RewriteRule\s\^/?api/rest~m';
            $contents       = @file_get_contents( rtrim(Mage::getBaseDir(), DS) . DS . '.htaccess' );
        } else if (preg_match('/nginx/im', $server)) {
            $expectedString = '~^[\s\r\n]*[^#]*rewrite\s\^/?api/rest~m';
            $contents       = '';
            $paths          = array('/etc/nginx/nginx.conf', '/etc/nginx/conf.d/*', '/etc/nginx/sites-enabled/*');

            foreach ($paths as $path) {
                $files = (array) @glob($path);

                foreach ($files as $file) {
                    if ( is_file($file) && is_readable($file) ) {
                        $contents .= @file_get_contents($file);
                    }
                }
            }
        }

        return !( $contents && $expectedString && !preg_match($expectedString, $contents) );
    }

    /**
     * Determine whether the authorization endpoint is accessible.
     *
     * @param boolean $verifyPeer     Optional flag to control peer verification during test.
     * @param boolean $asRedirectTest Optional flag to control response based on redirect detection.
     * 
     * @return boolean
     */
    private function _checkOauthAuthorizationEndpoint($verifyPeer = true, $asRedirectTest = false)
    {
        $http = new Varien_Http_Adapter_Curl();
        $url  = Mage::getSingleton('groove_hubshoply/config')->getAdminUrl('adminhtml/oauth_authorize');

        $http->addOption(CURLOPT_NOBODY, true);

        if (!$verifyPeer) {
            $http->addOption(CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($asRedirectTest) {
            $http->addOption(CURLOPT_FOLLOWLOCATION, true);
        }

        $http->write('GET', $url);

        $response = $http->read();

        $http->close();

        return in_array(
            Zend_Http_Response::extractCode($response),
            ( $asRedirectTest ? array(301, 302) : array(200, 401, 403) )
        );
    }

    /**
     * Determine whether the proprietary HubShop.ly API endpoint is accessible.
     * 
     * @return boolean
     */
    private function _checkHubshoplyAuthorizationEndpoint()
    {
        $http = new Varien_Http_Adapter_Curl();
        $url  = Mage::getSingleton('groove_hubshoply/config')->getFrontendUrl('hubshoply/queue/authenticate');

        $http->addOption(CURLOPT_NOBODY, true);

        $http->write('GET', $url);

        $response = $http->read();

        $http->close();

        return in_array(
            Zend_Http_Response::extractCode($response),
            array(200, 401, 403)
        );
    }

    /**
     * Generate a one-time use HubShop.ly token for this test.
     *
     * - Uses an existing authorized token if available.
     * - Autogenerates a new pre-authorized token if needed.
     *
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * 
     * @return Groove_Hubshoply_Model_Token
     */
    private function _generateHubshoplyToken(Mage_Oauth_Model_Consumer $consumer)
    {
        $collection = Mage::getResourceModel('groove_hubshoply/token_collection')
            ->addFieldToFilter('consumer_id', $consumer->getId())
            ->addFieldToFilter('expires', array('lt' => now()));

        if (!$collection->getSize()) {
            $token = Mage::getModel('groove_hubshoply/token')
                ->setConsumerId($consumer->getId())
                ->setToken(Mage::helper('oauth')->generateToken())
                ->setExpires(Groove_Hubshoply_Model_Token::DAY)
                ->setIsTemporary(true)
                ->save();
        } else {
            $token = $collection->getFirstItem();
        }

        return $token;
    }

    /**
     * Generate a one-time use token for this test.
     *
     * - Uses an existing authorized token if available.
     * - Autogenerates a new pre-authorized token if needed.
     *
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * 
     * @return Mage_Oauth_Model_Token
     */
    private function _generateToken(Mage_Oauth_Model_Consumer $consumer)
    {
        $user       = Mage::getSingleton('admin/session')->getUser();
        $collection = Mage::getResourceModel('oauth/token_collection')
            ->addFieldToFilter('authorized', 1)
            ->addFilterByConsumerId($consumer->getId())
            ->addFilterByType(Mage_Oauth_Model_Token::USER_TYPE_ADMIN)
            ->addFilterByRevoked(false);

        if (!$collection->getSize()) {
            if ( !$user || !$user->getId() ) {
                Mage::throwException('No admin user found for token generation.');
            }

            $token = Mage::getModel('oauth/token')
                ->setCallbackUrl($consumer->getCallbackUrl())
                ->setConsumerId($consumer->getId())
                ->setAdminId($user->getId())
                ->setType(Mage_Oauth_Model_Token::TYPE_REQUEST)
                ->setAuthorized(1)
                ->setExpires( date( Varien_Date::DATETIME_PHP_FORMAT, ( strtotime( now() ) + 60 ) ) )
                ->setIsTemporary(true)
                ->convertToAccess();
        } else {
            $token = $collection->getFirstItem();
        }

        return $token;
    }

    /**
     * Generate a pre-configured OAuth/HTTP client.
     * 
     * @param Mage_Oauth_Model_Consumer $consumer  The consumer model.
     * @param Mage_Oauth_Model_Token    $mageToken The authorized Magento token.
     * 
     * @return Zend_Oauth_Client
     */
    private function _getClient(Mage_Oauth_Model_Consumer $consumer, Mage_Oauth_Model_Token $mageToken)
    {
        $config = array(
            'siteUrl'           => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
            'callbackUrl'       => $consumer->getCallbackUrl(),
            'consumerKey'       => $consumer->getKey(),
            'consumerSecret'    => $consumer->getSecret(),
        );

        $token  = new Zend_Oauth_Token_Access();
        $client = new Zend_Oauth_Client($config);

        $token->setToken($mageToken->getToken());
        $token->setTokenSecret($mageToken->getSecret());

        $client->setToken($token);

        return $client;
    }

    /**
     * Test the HubShop.ly queue API.
     * 
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * @param Mage_Oauth_Model_Token    $token    The authorized Magento token.
     * 
     * @return boolean
     */
    private function _testHubshoplyQueueApi(Mage_Oauth_Model_Consumer $consumer, Groove_Hubshoply_Model_Token $token)
    {
        $http       = new Varien_Http_Adapter_Curl();
        $headers    = array("X-Access-Token: {$token->getToken()}");
        $url        = Mage::getSingleton('groove_hubshoply/config')
            ->getFrontendUrl('hubshoply/queue/view', array('first' => 1));

        $http->write('GET', $url, null, $headers);

        $response = $http->read();

        $http->close();
        
        return Zend_Http_Response::extractCode($response) === 200;
    }

    /**
     * Try to confirm redirect rules as a possible integration problem.
     * 
     * @param Varien_Object $object The item to diagnose.
     * 
     * @return boolean
     */
    private function _tryRewriteRuleTest(Varien_Object $object, $status = self::STATUS_WARN)
    {
        if (!$this->_checkRedirectRule()) {
            $object->setStatus($status)
                ->setDetails('Magento API redirect rule was not found.');

            return false;
        }

        return true;
    }

    /**
     * Test the products REST API.
     * 
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * @param Mage_Oauth_Model_Token    $token    The authorized Magento token.
     * 
     * @return boolean
     */
    private function _testProductsApi(Mage_Oauth_Model_Consumer $consumer, Mage_Oauth_Model_Token $token)
    {
        $client = $this->_getClient($consumer, $token);

        $client->setHeaders(
                array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => '*/*',
                )
            )
            ->setMethod('GET')
            ->setUri(Mage::getSingleton('groove_hubshoply/config')->getFrontendUrl('api/rest/products'));

        $response = $client->request();

        return $response->extractCode($response->asString()) === 200;
    }

    /**
     * Return dependencies.
     * 
     * @return array
     */
    public function getDependencies()
    {
        return array(
            'enabled'   => self::STATUS_PASS,
            'consumer'  => self::STATUS_PASS,
        );
    }

    /**
     * Perform a sample REST sequence to validate connectivity.
     *
     * @param Varien_Object $object The item to diagnose.
     * 
     * @return void
     */
    public function run(Varien_Object $object)
    {
        $skipHubshoplyEndpointTest = false;

        $object->setStatus(self::STATUS_PASS);

        $this->_tryRewriteRuleTest($object);

        if ($this->_checkOauthAuthorizationEndpoint(null, true)) {
            $object->setStatus(self::STATUS_WARN)
                ->setDetails('Encountered redirect on authorization endpoint.');

            // Skip to allow this warning to have greater severity
            $skipHubshoplyEndpointTest = true;
        } else if (!$this->_checkOauthAuthorizationEndpoint(false)) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('Authorization endpoint could not be reached.');

            return;
        } else if ( self::VERIFY_PEER && !$this->_checkOauthAuthorizationEndpoint() ) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('SSL peer verification failed on authorization endpoint.');

            return;
        }

        if ( !$skipHubshoplyEndpointTest && !$this->_checkHubshoplyAuthorizationEndpoint() ) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('HubShop.ly queue endpoint could not be reached.');
        }

        try {
            $consumer = Mage::helper('groove_hubshoply/oauth')->getConsumer(null, false);
        } catch (Mage_Core_Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Consumer validation error: %s', $error->getMessage()));

            return;
        } catch (Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Internal server error on consumer validation: %s', $error->getMessage()));

            return;
        }

        try {
            $token = $this->_generateToken($consumer);
        } catch (Mage_Core_Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Token validation error: %s', $error->getMessage()));

            return;
        } catch (Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Internal server error on token validation: %s', $error->getMessage()));

            return;
        }

        if (!$this->_testProductsApi($consumer, $token)) {
            if ($this->_tryRewriteRuleTest($object, self::STATUS_FAIL)) {
                $object->setStatus(self::STATUS_FAIL)
                    ->setDetails('Products API test did not succeed.');
            }
        }

        if ($token->getIsTemporary()) {
            $token->delete();
        }

        if ( $object->getStatus() === self::STATUS_FAIL ) {
            return;
        }
        try {
            $hubshoplyToken = $this->_generateHubshoplyToken($consumer);
        } catch (Mage_Core_Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('HubShop.ly token validation error: %s', $error->getMessage()));

            return;
        } catch (Exception $error) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails(sprintf('Internal server error on HubShop.ly token validation: %s', $error->getMessage()));

            return;
        }

        if (!$this->_testHubshoplyQueueApi($consumer, $hubshoplyToken)) {
            $object->setStatus(self::STATUS_FAIL)
                ->setDetails('HubShop.ly queue test did not succeed.');
        }

        if ($hubshoplyToken->getIsTemporary()) {
            $hubshoplyToken->delete();
        }

        $object->setStatus(self::STATUS_PASS)
            ->setDetails('');
    }

}