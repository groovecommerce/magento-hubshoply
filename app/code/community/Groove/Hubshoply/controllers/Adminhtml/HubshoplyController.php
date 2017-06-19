<?php

/**
 * HubShop.ly Magento
 * 
 * Admin configuration controller.
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
 * @category Class_Type_Controller
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Adminhtml_HubshoplyController
    extends Mage_Adminhtml_Controller_Action
{

    /**
     * Send the report for customer support.
     * 
     * @param array   $data    The report data.
     * @param integer $storeId The store ID for context.
     * 
     * @return Varien_Object
     */
    private function _sendReport(array $data, $storeId)
    {
        $store      = Mage::app()->getStore($storeId);
        $result     = new Varien_Object();
        $recipient  = Mage::getStoreConfig('hubshoply/support/contact', $store->getId());
        $mailer     = Mage::getModel('core/email_template');
        $contents   = Mage::helper('core')->jsonEncode($data);
        $attachment = $mailer->getMail()->createAttachment($contents);

        $attachment->type       = 'application/json';
        $attachment->filename   = 'hubshoply-diagnostics.json';

        $mailer->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $store->getId()));
        $mailer->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $store->getId()));
        $mailer->setTemplateSubject('Magento HubShop.ly Diagnostics Report');
        $mailer->setTemplateText("
            <!DOCTYPE html>
            <html>
                <head></head>
                <body>
                    <p>Diagnostics report sent from: {$store->getName()}</p>
                    <p>Store Owner: {$mailer->getSenderName()} ({$mailer->getSenderEmail()})</p>
                </body>
            </html>
        ");

        $status = $mailer->send(
            $recipient,
            'Magento HubShop.ly Support',
            array()
        );

        $result->setStatus($status)
            ->setRecipient($recipient);

        return $result;
    }

    /**
     * Write the report to disk for hard reference.
     * 
     * @param array $data The report data.
     * 
     * @return Varien_Object
     */
    private function _writeReport(array $data)
    {
        $filename   = 'hubshoply-diagnostics-' . date('Ymd_his') . '.json';
        $path       = rtrim(Mage::getBaseDir('var'), DS) . DS . $filename;
        $file       = new Varien_Object();
        $io         = new Varien_Io_File();

        $io->setAllowCreateFolders(true)
            ->open(array('path' => dirname($path)));

        $result = $io->write($filename, Mage::helper('core')->jsonEncode($data), 0755);

        if ($result) {
            $file->setPath($path)
                ->setUrl($this->getUrl('adminhtml/hubshoply/downloadReportDirect', array('file' => $filename)));
        }

        return $file;
    }

    /**
     * Download diagnostics report action.
     *
     * - Outputs JSON with generated file URL.
     * 
     * @return void
     */
    public function downloadReportAction()
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json');

        try {
            $helper = Mage::helper('core');
            $data   = $helper->jsonDecode($this->getRequest()->getParam('data'));
            $output = array('url' => '');

            if (empty($data)) {
                throw new Exception('No report data specified.');
            }

            $file           = $this->_writeReport($data);
            $output['url']  = $file->getUrl();

            $this->getResponse()
                ->setBody($helper->jsonEncode($output));
        } catch (Exception $error) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody($error->getMessage());
        }
    }

    /**
     * Process requests for direct report download.
     * 
     * @return void
     */
    public function downloadReportDirectAction()
    {
        $filename   = $this->getRequest()->getParam('file');
        $path       = rtrim(Mage::getBaseDir('var'), DS) . DS . $filename;

        if (!is_readable($path)) {
            $this->_redirectReferer();
        }

        $contents = @file_get_contents($path);

        if (!$contents) {
            $this->_getSession()->addError($this->__('Failed to get report file contents.'));

            return $this->_redirectReferer();
        }

        @unlink($path);

        $this->_prepareDownloadResponse($filename, $contents, 'application/json');
    }

    /**
     * Reset installation state action.
     * 
     * @return void
     */
    public function resetAction()
    {
        try {
            $setup      = new Groove_Hubshoply_Model_Resource_Setup('core_setup');
            $storeId    = Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();

            $setup->resetState($storeId);
        } catch (Groove_Hubshoply_SetupException $error) {
            $this->_getSession()->addError($error->getMessage());
        } catch (Exception $error) {
            $this->_getSession()->addError($this->__('Failed to reset setup state. Please contact support.' . $error->getMessage()));
        }

        $this->_redirect(
            'adminhtml/system_config/edit',
            array(
                'section'   => 'hubshoply',
                'website'   => $this->getRequest()->getParam('website'),
                'store'     => $this->getRequest()->getParam('store'),
            )
        );
    }

    /**
     * Send diagnostics report action.
     *
     * - Outputs JSON with resulting message.
     * 
     * @return void
     */
    public function sendReportAction()
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json');

        try {
            $helper  = Mage::helper('core');
            $storeId = Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();
            $data    = $helper->jsonDecode($this->getRequest()->getParam('data'));
            $output  = array('message' => '');

            if (empty($data)) {
                throw new Exception('No report data specified.');
            }

            $result = $this->_sendReport($data, $storeId);

            if ($result->getStatus()) {
                $output['message'] = $this->__('Report successfully sent to %s.', $result->getRecipient());
            } else {
                $output['message'] = $this->__('Failed to send report.');
            }

            $this->getResponse()
                ->setBody($helper->jsonEncode($output));
        } catch (Exception $error) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody($error->getMessage());
        }
    }

    /**
     * Start initial setup action.
     * 
     * @return void
     */
    public function startAction()
    {
        try {
            $setup      = new Groove_Hubshoply_Model_Resource_Setup('core_setup');
            $userId     = Mage::getSingleton('admin/session')->getUser()->getId();
            $storeId    = Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();

            $setup->autoInstall($userId, $storeId);
        } catch (Groove_Hubshoply_SetupException $error) {
            $this->_getSession()->addError($error->getMessage());
        } catch (Exception $error) {
            $this->_getSession()->addError($this->__('Failed to start setup. Please contact support.'));
        }

        $this->_redirect(
            'adminhtml/system_config/edit',
            array(
                'section'   => 'hubshoply',
                'website'   => $this->getRequest()->getParam('website'),
                'store'     => $this->getRequest()->getParam('store'),
            )
        );
    }

    /**
     * Run tests action.
     *
     * - Outputs re-generated report block HTML.
     * 
     * @return void
     */
    public function testAction()
    {
        $storeId    = Mage::app()->getStore($this->getRequest()->getParam('store'))->getId();
        $results    = Mage::getModel('groove_hubshoply/diagnostic')->run(array(), $storeId);
        $form       = new Varien_Data_Form();
        $element    = new Varien_Data_Form_Element_Label();
        $block      = $this->getLayout()
            ->createBlock('groove_hubshoply/adminhtml_system_config_field_diagnostics');

        $form->setHtmlIdPrefix('hubshoply');
        $element->setForm($form);
        $element->setLabel($this->__('Diagnostics'));
        $block->setResults($results);

        $this->getResponse()
            ->setHeader('Content-Type', 'text/html')
            ->setBody($block->render($element))
            ->sendResponse();

        exit;
    }
    
}