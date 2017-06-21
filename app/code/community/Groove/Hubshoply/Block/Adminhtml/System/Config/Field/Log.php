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

class Groove_Hubshoply_Block_Adminhtml_System_Config_Field_Log
    extends Mage_Adminhtml_Block_Widget_Grid
        implements Varien_Data_Form_Element_Renderer_Interface
{

    /* @var $_element Varien_Data_Form_Element_Abstract */
    protected $_element;

    /**
     * Setup grid options.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(false);
    }

    /**
     * Post-render callback.
     *
     * - Wraps markup with minimum requirements to fit the containing field row.
     * 
     * @param string $html string
     * 
     * @return string
     */
    protected function _afterToHtml($html = '')
    {
        $html   = parent::_afterToHtml($html);
        $notice = $this->__(
            'Records are kept for up to %d days.',
            floor( Groove_Hubshoply_Model_Config::LOG_ENTRY_LIFETIME / 60 / 60 / 24 )
        );

        return sprintf('
                <tr>
                    <td colspan="5">
                        <div>%s</div>
                        <p class="note">%s</p>
                    </td>
                </tr>
            ',
            $html,
            $notice
        );
    }

    /**
     * Prepare the resource model collection data for the grid.
     * 
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {  
        if (!$this->getCollection()) {
            $collection = Mage::getResourceModel('groove_hubshoply/log_collection');

            $this->setCollection($collection);
        }

        return parent::_prepareCollection();
    }

    /**
     * Prepare the grid columns.
     * 
     * @return Groove_Hubshoply_Block_Adminhtml_System_Config_Field_Diagnostics
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'log_id', 
            array(
                'header'    => $this->__('ID'),
                'index'     => 'log_id',
                'width'     => '100px',
            )
        );

        $this->addColumn(
            'level',
            array(
                'header'            => $this->__('Level'),
                'width'             => '100px',
                'index'             => 'level',
                'frame_callback'    => array($this, 'renderLogLevel'),
            )
        );

        $this->addColumn(
            'created_at',
             array(
                'header'    => $this->__('Date'),
                'type'      => 'datetime',
                'index'     => 'created_at',
                'width'     => '175px',
            )
        );

        $this->addColumn(
            'store_id',
             array(
                'header'            => $this->__('Store View'),
                'index'             => 'store_id',
                'width'             => '200px',
                'frame_callback'    => array($this, 'renderStoreName'),
            )
        );

        $this->addColumn(
            'message',
            array(
                'header'    => $this->__('Message'),
                'index'     => 'message',
            )
        );
 
        return parent::_prepareColumns();
    }

    /**
     * Get the main buttons HTML.
     *
     * - Extended to add clear button.
     * 
     * @return string
     */
    public function getMainButtonsHtml()
    {
        $html   = parent::getMainButtonsHtml();
        $block  = $this->getLayout()
            ->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'label'     => Mage::helper('adminhtml')->__('Clear Log'),
                    'onclick'   => "setLocation('{$this->getUrl('adminhtml/hubshoply/clearLog')}');",
                )
            );

        return $block->toHtml() . $html;
    }

    /**
     * Render the log level column values.
     * 
     * @param string                                  $value    The column value.
     * @param Mage_Core_Model_Abstract                $row      The model at the current row.
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column   The current column block.
     * @param boolean                                 $isExport Optional export flag.
     * 
     * @return string
     */
    public function renderLogLevel(
        $value, 
        Mage_Core_Model_Abstract $row, 
        Mage_Adminhtml_Block_Widget_Grid_Column $column, 
        $isExport = false
    )
    {
        $classification = array(
            Zend_Log::EMERG     => array('grid-severity-critical', 'EMERG'),
            Zend_Log::ALERT     => array('grid-severity-critical', 'ALERT'),
            Zend_Log::CRIT      => array('grid-severity-critical', 'CRIT'),
            Zend_Log::ERR       => array('grid-severity-major', 'ERR'),
            Zend_Log::WARN      => array('grid-severity-minor', 'WARN'),
            Zend_Log::NOTICE    => array('grid-severity-notice', 'NOTICE'),
            Zend_Log::INFO      => array('grid-severity-notice', 'INFO'),
            Zend_Log::DEBUG     => array('grid-severity-notice', 'DEBUG'),
        );

        $class  = 'grid-severity-notice';
        $label  = 'N/A';
        $html   = '<span class="%s"><span>%s</span></span>';

        if (!empty($classification[$value])) {
            $class = $classification[$value][0];
            $label = $classification[$value][1];
        }

        return sprintf($html, $class, $label);
    }

    /**
     * Render the store view column values.
     * 
     * @param string                                  $value    The column value.
     * @param Mage_Core_Model_Abstract                $row      The model at the current row.
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column   The current column block.
     * @param boolean                                 $isExport Optional export flag.
     * 
     * @return string
     */
    public function renderStoreName(
        $value, 
        Mage_Core_Model_Abstract $row, 
        Mage_Adminhtml_Block_Widget_Grid_Column $column, 
        $isExport = false
    )
    {
        return Mage::app()->getStore($value)->getName();
    }

    /**
     * Get the element facade.
     * 
     * @return Varien_Data_Form_Element_Abstract
     */
    public function getElement()
    {
        return $this->_element;
    }

    /**
     * Generate the row click URL.
     * 
     * @param Varien_Object $row The model at the current row.
     * 
     * @return string
     */
    public function getRowUrl($row)
    {
        return false;
    }

    /**
     * Render implementation.
     * 
     * @param Varien_Data_Form_Element_Abstract $element The element.
     * 
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $this->_element = $element;

        return $this->toHtml();
    }

}