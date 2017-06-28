<?php

/**
 * HubShop.ly Magento
 * 
 * Feature configuration model.
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

require_once rtrim(Mage::getModuleDir('etc', 'Groove_Hubshoply'), DS) . DS . '..' . DS . 'functions.php';

class Groove_Hubshoply_Model_Config
    extends Mage_Core_Model_Abstract
{

    const LOG_ENTRY_LIFETIME                = 604800;
    const OAUTH_CONSUMER                    = 'HubShop.ly';
    const REMOTE_AUTH_URL                   = 'https://magento.hubshop.ly/auth/magento';
    const REMOTE_TEST_AUTH_URL              = 'https://hubshoply-magento-staging.herokuapp.com/auth/magento';
    const ROLE_NAME                         = 'HubShop.ly';
    const TRACKING_SCRIPT_URI               = '//magento.hubshop.ly/shops';
    const TRACKING_SCRIPT_TEST_URI          = '//hubshoply-magento-staging.herokuapp.com/shops';

    const XML_CONFIG_PATH_ADMIN_URL         = 'hubshoply/advanced/admin_url';
    const XML_CONFIG_PATH_DIAGNOSTIC_TESTS  = 'global/hubshoply/diagnostic/tests';
    const XML_CONFIG_PATH_ENABLED           = 'hubshoply/advanced/enabled';
    const XML_CONFIG_PATH_FRONTEND_URL      = 'hubshoply/advanced/frontend_url';
    const XML_CONFIG_PATH_SITE_ID           = 'hubshoply/advanced/site_id';
    const XML_CONFIG_PATH_TEST_MODE         = 'hubshoply/advanced/test_mode';
    const XML_CONFIG_PATH_TRACK_CUSTOMERS   = 'hubshoply/advanced/track_customers';
    const XML_CONFIG_PATH_USER_CONFIG       = 'hubshoply/advanced/user_config';

    protected $_eventPrefix                 = 'hubshoply_config'; // Observe after-load to add custom config
    protected $_options                     = array();

    /**
     * Local constructor.
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('groove_hubshoply/config');

        // Autoload data
        $this->load();
    }

    /**
     * Change the URL scheme if the current secure context would contradict frontend URL settings.
     *
     * Criteria:
     *
     *  - Given URL scheme is HTTPS
     *  - Store secure URL is configured using HTTPS; or,
     *  - Store is configured not to use secure URLs in frontend
     * 
     * @param array &$data A parsed URL.
     * 
     * @return void
     */
    private function _adjustFrontendUrlScheme(array &$data)
    {
        if (
            !empty($data['scheme']) &&
            strcasecmp($data['scheme'], 'https') === 0 &&
            (
                strcasecmp('https', substr(Mage::getStoreConfig('web/secure/base_url'), 0, 5)) !== 0 ||
                !Mage::getStoreConfigFlag('web/secure/use_in_frontend')
            )
        ) {
            $data['scheme'] = 'http';
        }
    }

    /**
     * Apply modifications to the given target path, a la Wacky Wednesday style.
     *
     * Available Modifiers
     * -------------------
     *
     *      [!] Subtracts the given term from the target path
     *      [>] Adds the given term to the target path
     *
     * Example
     * -------
     *
     *      Store Base URL:
     *      - http://www.shop.com/us/
     *
     *      Custom Frontend URL:
     *      - http://www.shop.com/!us>service
     *
     *      Result:
     *      - http://www.shop.com/service/
     *
     * Explanation
     * -----------
     *
     * Path modifiers are designed to give extra control to custom frontend URLs.
     * They are designed specifically to address Magento shops on a subfolder for
     * which the native REST API has no support.
     *
     * For example, consider this store URL:
     *  - http://www.shop.com/us/
     *
     * Unless the directory is a real server path with `api.php` in it, the URL
     * assembly and callbacks would fail. In such a case, the modifier works to
     * augment the path component of the store base URL in order to comply with
     * REST API endpoint expectations.
     *
     * Using a custom frontend URL alone is not sufficient, because the algorithm
     * for merging custom URLs with normal URLs cannot detect which part of a path
     * was for the base URL and which part was for the application route.
     *
     * Therefore, using the example above, we could would a modifier like so:
     * - http://www.shop.com/!us
     *
     * Which would assemble to a REST endpoint like the following:
     * - http://www.shop.com/api/rest/products
     * 
     * @param string &$expressionPath The target path modifier expression.
     * @param string &$targetPath     The target path.
     * 
     * @return void
     */
    private function _applyUrlPathModifiers(&$expressionPath, &$targetPath)
    {
        if (is_array($expressionPath)) {
            $expressionPath = &$expressionPath['path'];
        }

        if (is_array($targetPath)) {
            $targetPath = &$targetPath['path'];
        }

        preg_match_all('/([!+]*)([\w\d\/]*)/', $expressionPath, $components);

        if (!empty($components[2])) {
            foreach ($components[1] as $index => $modifier) {
                switch ($modifier) {
                    case '!' : 
                        $targetPath = str_replace($components[2][$index], '', $targetPath);
                        break;
                    case '>' :
                        $targetPath .= $components[2][$index];
                        break;
                    default :
                        break;
                }
            }

            // Clear the expression when done so it doesn't end up in the assembled URL
            $expressionPath = '';
        }
    }

    /**
     * Get the configured host name for the requested store.
     * 
     * @param integer $storeId The target store ID.
     * 
     * @return string
     */
    private function _getStoreUrlHost($storeId)
    {
        $parts = parse_url(
            Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB)
        );

        return (string) $parts['host'];
    }

    /**
     * Translate a configuration string.
     *
     * Expected format:
     * 
     *   foo=bar
     *   baz={{var qux}}
     *
     * Output:
     *
     *   array('foo' => 'bar', 'baz' => 'thud')
     * 
     * @param string $input The input configuration.
     * 
     * @return array
     */
    private function _translateConfig($input)
    {
        try {
            $output     = array();
            $processor  = new Varien_Filter_Template();
            $values     = array_filter( ( preg_split('/[\r\n]+/', $input) ) );

            $variables  = array(
                'id'        => $this->getSiteId(),
                'store'     => Mage::app()->getStore(),
                'category'  => ( Mage::registry('current_category') ?: new Varien_Object() ),
                'product'   => ( Mage::registry('current_product') ?: new Varien_Object() ),
                'order'     => ( Mage::registry('current_order') ?: new Varien_Object() ),
                'customer'  => Mage::getSingleton('customer/session')->getCustomer(),
            );

            $processor->setVariables($variables);

            foreach ($values as $row) {
                $value = explode( '=', ( preg_replace('/\s+=\s+/', '=', $row) ) );

                if ( count($value) === 2 ) {
                    $output[$value[0]] = $processor->filter($value[1]);
                }
            }
        } catch (Exception $error) {
            Mage::logException($error);

            $output = array();
        }

        return $output;
    }

    /**
     * Post-load handler.
     *
     * - Resets data with real configuration options.
     * 
     * @return void
     */
    protected function _afterLoad()
    {
        if (!empty($this->_data['value'])) {
            $systemConfig = $this->_translateConfig($this->_data['value']);
        } else {
            $systemConfig = array();
        }

        $this->_data = array();

        $this->addData($systemConfig);

        if ($this->canTrackCustomers(true)) {
            $this->setData('customerEmail', Mage::getSingleton('customer/session')->getCustomer()->getEmail());
        }

        return $this;
    }

    /**
     * Determine whether customers may be tracked.
     *
     * @param boolean $checkLogin Optional flag to consider login status.
     * @param integer $storeId    Store ID for context.
     * 
     * @return boolean
     */
    public function canTrackCustomers($checkLogin = false, $storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PATH_TRACK_CUSTOMERS, $storeId) &&
            ( !$checkLogin || Mage::getSingleton('customer/session')->isLoggedIn() );
    }

    /**
     * Get a collection of HubShop.ly-enabled stores.
     * 
     * @param boolean $withDefault Optional flag to force-include the admin store view.
     * 
     * @return array
     */
    public function getActiveStores($withDefault = false)
    {
        $stores = array();

        foreach (Mage::app()->getStores($withDefault) as $store) {
            if ($this->isEnabled($store->getId())) {
                $stores[$store->getId()] = $store;
            }
        }

        return $stores;
    }

    /**
     * Generate a remote callback-safe admin URL.
     *
     * @param string  $route   The target admin route.
     * @param array   $params  Optional URL parameters.
     * @param integer $storeId Store ID for context.
     * 
     * @return string
     */
    public function getAdminUrl($route, array $params = array(), $storeId = null)
    {
        $helper     = Mage::helper('adminhtml');
        $customUrl  = parse_url(Mage::getStoreConfig(self::XML_CONFIG_PATH_ADMIN_URL, $storeId));
        $urlData    = parse_url($helper->getUrl($route, $params));

        if (!empty($customUrl['path'])) {
            $customUrl['path'] = rtrim($customUrl['path'], '/');

            if (empty($urlData['path'])) {
                $urlData['path'] = '';
            }

            $customUrl['path'] = $customUrl['path'] . str_replace($customUrl['path'], '', $urlData['path']);
        }

        // @see bundled functions.php for polyfill
        return http_build_url(
            array_merge(
                array_filter($urlData),
                array_filter($customUrl)
            )
        );
    }

    /**
     * Get the remote service authorization endpoint.
     *
     * @param integer $storeId Store ID for context.
     * 
     * @return string
     */
    public function getAuthUrl($storeId = null)
    {
        return $this->isTestMode($storeId) ? self::REMOTE_TEST_AUTH_URL : self::REMOTE_AUTH_URL;
    }

    /**
     * Generate a remote callback-safe frontend URL.
     *
     * @param string  $route   The target frontend route.
     * @param array   $params  Optional URL parameters.
     * @param integer $storeId Store ID for context.
     * 
     * @return string
     */
    public function getFrontendUrl($route = '', array $params = array(), $storeId = null)
    {
        $urlModel   = Mage::getModel('core/url')->setStore(Mage::app()->getStore($storeId));
        $urlData    = parse_url($urlModel->getUrl($route, $params));
        $customUrl  = parse_url(Mage::getStoreConfig(self::XML_CONFIG_PATH_FRONTEND_URL, $storeId));

        // REST API rewrite compatibility fix
        $urlData['path'] = ltrim( str_replace( 'index.php', '', ( rtrim( $urlData['path'], '/' ) ) ), '/' );

        $this->_applyUrlPathModifiers($customUrl, $urlData);

        if (!empty($customUrl['path'])) {
            $customUrl['path'] = ltrim( str_replace( 'index.php', '', ( rtrim( $customUrl['path'], '/') ) ), '/' );

            if (empty($urlData['path'])) {
                $urlData['path'] = '';
            }

            $customUrl['path'] = $customUrl['path'] . str_replace($customUrl['path'], '', $urlData['path']);
        }

        $urlData['path'] .= '/';

        $this->_adjustFrontendUrlScheme($urlData);

        // @see bundled functions.php for polyfill
        return http_build_url(
            array_merge(
                array_filter($urlData),
                array_filter($customUrl)
            )
        );
    }

    /**
     * Return the configured site ID.
     *
     * @param boolean $canGenerateFromHost Optional flag to control ID lookup fallback.
     * @param integer $storeId             Store ID for context.
     * 
     * @return string
     */
    public function getSiteId($canGenerateFromHost = true, $storeId = null)
    {
        $id = (string) Mage::getStoreConfig(self::XML_CONFIG_PATH_SITE_ID, $storeId);

        if ( empty($id) && $canGenerateFromHost ) {
            $currentHost    = Mage::helper('core/http')->getHttpHost();
            $storeHost      = $this->_getStoreUrlHost($storeId);

            if ( $currentHost === $storeHost || !$storeId ) {
                $id = md5($currentHost);
            } else {
                $id = md5($storeHost);
            }
        }

        return $id;
    }

    /**
     * Generate a URL to the tracking script.
     *
     * @param integer $storeId Store ID for context.
     * 
     * @return string
     */
    public function getTrackingScriptUrl($storeId = null)
    {
        $siteId = $this->getSiteId(true, $storeId);
        $uri    = $this->isTestMode() ? self::TRACKING_SCRIPT_TEST_URI : self::TRACKING_SCRIPT_URI;

        return rtrim($uri, '/') . "/{$siteId}.js";
    }

    /**
     * Determine whether the feature is enabled.
     *
     * @param integer $storeId Store ID for context.
     * 
     * @return boolean
     */
    public function isEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PATH_ENABLED, $storeId);
    }

    /**
     * Determine whether test mode is enabled.
     * 
     * @param integer $storeId The store ID for context.
     * 
     * @return boolean
     */
    public function isTestMode($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PATH_TEST_MODE, $storeId);
    }

    /**
     * Custom load implementation.
     * 
     * @param string $id    The record ID [ignored].
     * @param string $field The target table column [ignored].
     * 
     * @return Groove_Hubshoply_Model_Config
     */
    public function load($id = null, $field = null)
    {
        parent::load(self::XML_CONFIG_PATH_USER_CONFIG, 'path');

        return $this;
    }

    /**
     * Retrieve the diagnostic test configuration.
     * 
     * @return Varien_Simplexml_Element
     */
    public function getDiagnosticTests()
    {
        return Mage::getConfig()->getNode(self::XML_CONFIG_PATH_DIAGNOSTIC_TESTS);
    }

}