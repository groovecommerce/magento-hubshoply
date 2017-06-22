<?php

/**
 * Module setup resource model.
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

class Groove_Hubshoply_Model_Resource_Setup
    extends Mage_Core_Model_Resource_Setup
{

    /**
     * Get the currently logged in admin user ID.
     * 
     * @return integer
     */
    private function _getCurrentAdminUserId()
    {
        $user = Mage::getSingleton('admin/session')->getUser();

        if (!$user) {
            return 0;
        }

        return $user->getId();
    }

    /**
     * Enable features at the given scope.
     *
     * @param integer $storeId The store ID for context.
     * 
     * @return Groove_Hubshoply_Model_Resource_Setup
     */
    public function enableFeatures($storeId = null)
    {
        $scope = 'stores';

        if (!$storeId) {
            $scope      = 'default';
            $storeId    = null;
        }

        Mage::getConfig()
            ->saveConfig(Groove_Hubshoply_Model_Config::XML_CONFIG_PATH_ENABLED, '1', $scope, $storeId)
            ->saveConfig(Groove_Hubshoply_Model_Config::XML_CONFIG_PATH_TRACK_CUSTOMERS, '1', $scope, $storeId)
            ->cleanCache();

        return $this;
    }

    /**
     * Install an admin role for REST setup.
     * 
     * @return Mage_Api2_Model_Acl_Global_Role
     */
    public function provisionRole()
    {
        try {
            $role = Mage::getResourceModel('api2/acl_global_role_collection')
                ->addFieldToFilter('role_name', Groove_Hubshoply_Model_Config::ROLE_NAME)
                ->getFirstItem();

            if (!$role->getId()) {
                $role->setRoleName(Groove_Hubshoply_Model_Config::ROLE_NAME)
                    ->save();
            } else {
                // Purge any assigned rules
                Mage::getResourceModel('api2/acl_global_rule_collection')
                    ->addFilterByRoleId($role->getId())
                    ->walk('delete');
            }

            $rule = Mage::getModel('api2/acl_global_rule')
                ->setRoleId($role->getId())
                ->setResourceId(Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL)
                ->setPrivilege(null)
                ->save();
        } catch (Exception $error) {
            throw new Groove_Hubshoply_SetupException($error->getMessage());
        }

        return $role;
    }

    /**
     * Assign the given user to the given role.
     * 
     * @param integer $userId The target user ID.
     * @param integer $roleId The target role ID.
     * 
     * @return Groove_Hubshoply_Model_Resource_Setup
     */
    public function assignUserToRole($userId, $roleId)
    {
        try {
            Mage::getResourceSingleton('api2/acl_global_role')
                ->saveAdminToRoleRelation($userId, $roleId);
        } catch (Exception $error) {
            throw new Groove_Hubshoply_SetupException($error->getMessage());
        }

        return $this;
    }

    /**
     * Automate system setup for HubShop.ly integration.
     * 
     * @param integer $targetUserId The target admin user ID under which to install.
     * @param integer $storeId      The store ID for context.
     * 
     * @return Groove_Hubshoply_Model_Resource_Setup
     */
    public function autoInstall($targetUserId = null, $storeId = null)
    {
        $this->enableFeatures($storeId);

        if (is_null($targetUserId)) {
            $targetUserId = $this->_getCurrentAdminUserId();
        }

        $user = Mage::getModel('admin/user')->load($targetUserId);

        if (!$user->getId()) {
            throw new Groove_Hubshoply_SetupException(
                sprintf('Failed to find admin user by ID "%d"', $targetUserId)
            );
        }

        $role = $this->provisionRole();

        $this->assignUserToRole($user->getId(), $role->getId());

        $this->setupConsumer($storeId);

        Mage::helper('groove_hubshoply/debug')->log('HubShop.ly system installation completed.', Zend_Log::NOTICE);

        return $this;
    }

    /**
     * Reset installation state.
     *
     * @param integer $storeId The store ID for context.
     * 
     * @return Groove_Hubshoply_Model_Resource_Setup
     */
    public function resetState($storeId = null)
    {
        $collection = Mage::getResourceModel('core/config_data_collection');
        $path       = 'hubshoply';
        $scope      = 'default';
        $scopeId    = null;

        if ( $storeId > 0 ) {
            $scope      = 'stores';
            $scopeId    = $storeId;
        }

        $collection->addScopeFilter($scope, $scopeId, $path)
            ->walk('delete');

        Mage::getConfig()->cleanCache();

        Mage::helper('groove_hubshoply/oauth')
            ->getConsumer(null, true, $storeId)
            ->delete();

        $this->getConnection('core_write')
            ->truncateTable($this->getTable('groove_hubshoply/queueitem'))
            ->truncateTable($this->getTable('groove_hubshoply/token'))
            ->truncateTable($this->getTable('groove_hubshoply/log'));
    }

    /**
     * Provision the OAuth consumer record.
     *
     * @param integer $storeId The store ID for context.
     * 
     * @return Mage_Oauth_Model_Consumer
     */
    public function setupConsumer($storeId)
    {
        $consumer = Mage::helper('groove_hubshoply/oauth')->getConsumer(null, false, $storeId);

        // Upgrade consumers from pre-stable releases
        if ( $consumer->getName() === Groove_Hubshoply_Model_Config::OAUTH_CONSUMER ) {
            $storeId = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
            
            $consumer->setName( Groove_Hubshoply_Model_Config::OAUTH_CONSUMER . " #{$storeId}" )
                ->setCallbackUrl(Mage::getSingleton('groove_hubshoply/config')->getAuthUrl($storeId))
                ->setWasUpgraded(true)
                ->save();
        } else if (!$consumer->getId()) {
            $consumer = Mage::helper('groove_hubshoply/oauth')->getConsumer(null, true, $storeId);
        }

        return $consumer;
    }

}