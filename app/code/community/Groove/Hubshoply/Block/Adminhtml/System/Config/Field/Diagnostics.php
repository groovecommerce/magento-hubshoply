<?php

/**
 * HubShop.ly Magento
 * 
 * Configuration diagnostics fieldset element renderer block.
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

class Groove_Hubshoply_Block_Adminhtml_System_Config_Field_Diagnostics
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

        $this->setTemplate('hubshoply/system/config/field/diagnostics.phtml');
    }

    /**
     * Generate a URL to the diagnostic test action.
     * 
     * @return string
     */
    public function getDiagnosticsUrl()
    {
        return $this->getUrl('adminhtml/hubshoply/test', array('_current' => true));
    }

    /**
     * Generate a URL to the diagnostic download action.
     * 
     * @return string
     */
    public function getDiagnosticsDownloadUrl()
    {
        return $this->getUrl('adminhtml/hubshoply/downloadReport', array('_current' => true));
    }

    /**
     * Generate a URL to the diagnostic download action.
     * 
     * @return string
     */
    public function getDiagnosticsSendUrl()
    {
        return $this->getUrl('adminhtml/hubshoply/sendReport', array('_current' => true));
    }

    /**
     * Generate an HTML tag for the item status.
     * 
     * @param Varien_Object $item The status item object.
     * 
     * @return string
     */
    public function getStatusIconHtml(Varien_Object $item)
    {
        $class = '';
        $label = '';

        switch ($item->getStatus()) {
            case Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_PASS :
                $class = 'grid-severity-notice';
                $label = $this->__('PASS');
                break;
            case Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_WARN :
                $class = 'grid-severity-minor';
                $label = $this->__('WARN');
                break;
            case Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_FAIL :
                $class = 'grid-severity-critical';
                $label = $this->__('FAIL');
                break;
            case Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_SKIP :
                $label = $this->__('SKIP');
                break;
            default :
                break;
        }

        return '
            <span class="item-status ' . $class . '" title="' . $item->getDetails() . '">
                <span>' . $label . '</span>
            </span>
            ' . ( $item->getDetails() ?  ( '<span>&mdash; ' . $item->getDetails() ) . '</span>' : '' );
    }

    /**
     * Get the test results collection.
     * 
     * @return Varien_Data_Collection
     */
    public function getResults()
    {
        if (!$this->_getData('results')) {
            $storeId = Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();

            $this->setData(
                'results',
                Mage::getModel('groove_hubshoply/diagnostic')->run(array(), $storeId)
            );
        }

        return $this->_getData('results');
    }

}