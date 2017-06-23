<?php

/**
 * HubShop.ly Magento
 * 
 * Configuration contact fieldset element renderer block.
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

class Groove_Hubshoply_Block_Adminhtml_System_Config_Field_Contact
    extends Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element
        implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * Local constructor.
     * 
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('hubshoply/system/config/field/contact.phtml');

        $this->_checkUpdates();
    }

    /**
     * Check for extension update availability.
     * 
     * @return void
     */
    private function _checkUpdates()
    {
        $feedUrl    = Mage::getStoreConfig('hubshoply/support/feed_url');
        $data       = (array) Mage::helper('core')->jsonDecode(@file_get_contents($feedUrl));

        if ( !empty($data) && !empty($data['hubshoply'] && !empty($data['hubshoply']['magento']) ) ) {
            $update = $data['hubshoply']['magento'];
            $latest = empty($update['latest']) ? $this->getVersion() : $update['latest']['version'];

            if ( version_compare($this->getVersion(), $latest) > 0 ) {
                $this->setData('update', new Varien_Object($update['latest']));
            }
        }
    }

    /**
     * Generate a URL to the diagnostic test action.
     * 
     * @return string
     */
    public function getSupportEmail()
    {
        return Mage::getStoreConfig('hubshoply/support/contact');
    }

    /**
     * Get the module version.
     * 
     * @return string
     */
    public function getVersion()
    {
        return (string) Mage::getConfig()->getNode('modules/Groove_Hubshoply/version');
    }

}