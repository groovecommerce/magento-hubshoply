<?php

/**
 * HubShop.ly Magento
 * 
 * Admin IFRAME field element block.
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
 * @category Class_Type_Block
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Block_Adminhtml_System_Config_Field_Connector
    extends Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element
{

    /**
     * Local constructor.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('hubshoply/connector.phtml');
    }

    /**
     * Get the current store ID from request parameters.
     * 
     * @return integer
     */
    private function _getStoreId()
    {
        return (int) Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();
    }

    /**
     * Prepare the global layout.
     *
     * - Suggests system provisioning before normal setup.
     * 
     * @return Groove_Hubshoply_Block_Adminhtml_Connector
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $helper = $this->helper('groove_hubshoply/oauth');
        $config = Mage::getSingleton('groove_hubshoply/config');

        if ($this->needsProvisioning()) {
            $this->setTemplate('hubshoply/provisioning-notice.phtml');
        } else {
            $this->setFrameSrc(
                preg_replace(
                    '~https?://~',
                    '//',
                    $helper->buildUrl($config->getAuthUrl($this->_getStoreId()), null, true)
                )
            );
        }

        return $this;
    }

    /**
     * Generate the URL to start store setup.
     * 
     * @return string
     */
    public function getStartUrl()
    {
        return $this->getUrl('adminhtml/hubshoply/start', array('_current' => true));
    }

    /**
     * Determine whether provisioning is needed.
     *
     * Provisioning needed if any are true:
     * 
     *  - Feature not enabled
     *  - OAuth consumer not found
     *  - REST role not found
     * 
     * @return boolean
     */
    public function needsProvisioning()
    {
        $tests      = array('enabled', 'consumer', 'role'); 
        $results    = Mage::getModel('groove_hubshoply/diagnostic')
            ->setSkipDependencyCheckFlag(true)
            ->run($tests, $this->_getStoreId());
            
        foreach ($results as $result) {
            if ( $result->getStatus() !== Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_PASS ) {
                return true;
            }
        }

        return false;
    }
    
}