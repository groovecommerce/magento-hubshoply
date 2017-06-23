<?php

/**
 * HubShop.ly Magento
 * 
 * Remote queue interface controller.
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

class Groove_Hubshoply_QueueController
    extends Mage_Core_Controller_Front_Action
{

    /**
     * Check for authorization. Halt with a 401 if unauthorized, else return token.
     * 
     * @return Groove_Hubshoply_Model_Token
     */
    private function _checkAuthorization()
    {
        $accessToken    = $this->getRequest()->getHeader('X-Access-Token');
        $tokenModel     = Mage::getModel('groove_hubshoply/token')->load($accessToken, 'token');

        if ( !$tokenModel->getId() || $tokenModel->isExpired() ) {
            $this->_sendError(
                401,
                'Unauthorized',
                'Your token is invalid or not present. Please re-authenticate and try again.',
                function ($response) { $response->setHeader('WWW-Authenticate', 'Custom', true); }
            );
        } else if (!$this->_isStoreEnabled()) {
            $this->_sendError(
                503,
                'Service Unavailable',
                'The HubShop.ly service is not currently enabled for this shop.'
            );
        } else {
            return $tokenModel;
        }
    }

    /**
     * Determine whether the current store is enabled for HubShop.ly.
     * 
     * @return boolean
     */
    private function _isStoreEnabled()
    {
        return Mage::getSingleton('groove_hubshoply/config')->isEnabled(Mage::app()->getStore()->getId());
    }

    /**
     * Trigger abandaned cart queue processing.
     *
     * @return void
     */
    private function _queueCarts()
    {
        Mage::getModel('groove_hubshoply/event')->abandonCartProcessing(new Varien_Event_Observer());
    }

    /**
     * Removes all queue items inclusively for the ID range given.
     * 
     * @param int $from The starting ID range value.
     * @param int $to   The ending ID range value.
     *
     * @return void
     */
    private function _removeFromRange($from, $to)
    {
        try {
            $this->_validateQueueIdRange($from, $to);

            Mage::getResourceModel('groove_hubshoply/queueitem_collection')
                ->addFieldToFilter('queue_id', array('from' => $from, 'to' => $to))
                ->walk('delete');
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logException($error);

            $this->_sendError(500, 'Failed to remove queue items from range.', $error);
        }
    }

    /**
     * Terminates request with given error details.
     *
     * @param integer  $code       An HTTP response code.
     * @param string   $message    An optional error message to include.
     * @param string   $details    Optional details about the error.
     * @param callable $callback   Optional callback to apply to the response.
     *
     * @return void
     */
    private function _sendError($code = null, $message = '', $details = '', $callback = null)
    {
        if ( (int) $code < 100 ) {
            $code = 500;
        }

        $data = array(
            'error_code'    => $code,
            'error_message' => $message,
            'error_details' => $details,
        );

        $this->getResponse()
            ->setHttpResponseCode($code)
            ->setBody(Mage::helper('core')->jsonEncode($data));

        if (is_callable($callback)) {
            $callback($this->getResponse());
        }

        $this->getResponse()
            ->sendResponse();

        exit;
    }

    /**
     * Validate the OAuth consumer data.
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * 
     * @return void
     */
    private function _validateConsumer(Mage_Oauth_Model_Consumer $consumer)
    {
        if (!$consumer->getId()) {
            throw new Mage_Oauth_Exception('Unrecognized OAuth client.', 401);
        }

        $helper = Mage::helper('groove_hubshoply');
        $secret = $this->getRequest()->getHeader('X-Auth-Secret');

        if ( !$helper->safeCompare($consumer->getSecret(), $secret) ) {
            throw new Mage_Oauth_Exception('OAuth secret key rejected.', 401);
        }
    }

    /**
     * Validate the queue ID range.
     * 
     * @param int $from The starting ID range value.
     * @param int $to   The ending ID range value.
     *
     * @return void
     */
    private function _validateQueueIdRange($from, $to)
    {
        if ( !( is_numeric($from) && is_numeric($to) && ($from <= $to) ) ) {
            $message = ($from === $to) ?
                "Queue ID provided [$from] is an invalid integer." :
                "Queue ID range provided [FROM $from, TO $to] is an invalid range of integers.";

            $this->_sendError(400, 'Bad Request', $message);
        }
    }

    /**
     * OAuth authentication action.
     *
     * @return void
     */
    public function authenticateAction()
    {
        try {
            $consumer = Mage::getModel('oauth/consumer')->load(
                $this->getRequest()->getHeader('X-Auth-Key'),
                'key'
            );
            
            Mage::helper('groove_hubshoply/debug')->log(
                sprintf('Request to authenticate on queue from %s', Mage::helper('core/http')->getRemoteAddr()),
                Zend_Log::INFO
            );

            $this->_validateConsumer($consumer);

            $token = Mage::getModel('groove_hubshoply/token')
                ->setConsumerId($consumer->getId())
                ->setToken(Mage::helper('oauth')->generateToken())
                ->setExpires(Groove_Hubshoply_Model_Token::DAY)
                ->save();

            $response = array(
                'token'   => $token->getToken(),
                'expires' => $token->getExpires(),
            );

            $this->getResponse()
                ->setHeader('X-Access-Token', $token->getToken())
                ->setHeader('X-Access-Expires', $token->getExpires())
                ->setHeader('Content-Type', 'application/json')
                ->setBody(Mage::helper('core')->jsonEncode($response))
                ->sendResponse();

            exit;
        } catch (Mage_Oauth_Exception $error) {
            $this->_sendError($error->getCode(), 'OAuth Exception', $error->getMessage());
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logException($error);

            $this->_sendError(500, 'Server Error', 'Exception thrown while authenticating this request.');
        }
    }

    /**
     * View action.
     *
     * A customizable view of queue items.
     * 
     * Route: hubshoply/queue/view/first/:count
     * Route: hubshoply/queue/view/last/:count
     * Route: hubshoply/queue/view/type/:event_type
     * Route: hubshoply/queue/view/entity/:event_entity
     * Route: hubshoply/queue/view/store/:store_id
     * Route: hubshoply/queue/view/limit/:offset,:count
     *
     * Most routes can be compounded.
     * FIRST, LAST, and LIMIT are exclusive and cannot be compounded.
     * 
     * - Example:
     *  
     *  hubshoply/queue/view/first/:count/store/:store_id/type/:type
     *
     * @return void
     */
    public function viewAction()
    {
        try {
            Mage::helper('groove_hubshoply/debug')->log(
                sprintf('Request to view queue from %s', Mage::helper('core/http')->getRemoteAddr()),
                Zend_Log::INFO
            );

            $this->_checkAuthorization();

            $request    = $this->getRequest();
            $collection = Mage::getResourceModel('groove_hubshoply/queueitem_collection');
            
            if ($request->getParam('first')) {
                $collection
                    ->setOrder('created_at', 'ASC')
                    ->setPageSize($request->getParam('first'));
            } else if ($request->getParam('last')) {
                $collection
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize($request->getParam('last'));
            } else if ($limit = $request->getParam('limit')) {
                $limit = explode(',', $limit);
                $collection->setOrder('created_at', 'ASC');

                $collection->getSelect()->limit($limit[1], $limit[0]);
            }

            if ($request->getParam('type')) {
                $collection->addFieldToFilter('event_type', $request->getParam('type'));
            }

            if ($request->getParam('entity')) {
                $collection->addFieldToFilter('event_entity', $request->getParam('entity'));
            }

            if ($request->getParam('store')) {
                $collection->addFieldToFilter('store_id', $request->getParam('store'));
            }
            
            $this->_queueCarts();

            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody($collection->getQueueCollectionJson())
                ->sendResponse();

            exit;
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logException($error);

            $this->_sendError(500, 'Failed to process view request.', $error->getMessage());
        }
    }

    /**
     * Mark action.
     * 
     * Route: hubshoply/queue/mark/id/:id
     * Route: hubshoply/queue/mark/from/:id/to/:id
     *
     * Delete the requested items from the queue.
     *
     * @return void
     */
    public function markAction()
    {
        try {
            $request = $this->getRequest();

            Mage::helper('groove_hubshoply/debug')->log(
                sprintf('Request to delete queue items from %s', Mage::helper('core/http')->getRemoteAddr()),
                Zend_Log::INFO
            );

            $this->_checkAuthorization();

            if (!$request->isDelete()) {
                $this->_sendError(
                    405,
                    'Method Not Allowed',
                    'Requested method is not allowed.',
                    function($response) { $response->setHeader('Allow', 'DELETE', true); }
                );
            } elseif ( ( $id = $request->getParam('id') ) ) {
                $this->_removeFromRange($id,$id);
            } elseif (
                ( $from = $request->getParam('from') ) &&
                ( $to = $request->getParam('to'))
            ) {
                $this->_removeFromRange($from, $to);
            } else {
                $this->_sendError(
                    400,
                    'Bad Request',
                    'URI needs to be in the format [hubshoply/mark/id/###] for single deletions or [hubshoply/mark/from/###/to/###].'
                );
            }

            exit;
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logException($error);

            $this->_sendError(500, 'Failed to process mark request.', $error->getMessage());
        }
    }
    
}