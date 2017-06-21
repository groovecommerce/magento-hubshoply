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
     * Validate the OAuth consumer data.
     * @param Mage_Oauth_Model_Consumer $consumer The consumer model.
     * 
     * @return void
     */
    private function _validateConsumer(Mage_Oauth_Model_Consumer $consumer)
    {
        if (!$consumer->getId()) {
            throw new Mage_Oauth_Exception(401, 'Unrecognized OAuth client.');
        }

        $helper = Mage::helper('groove_hubshoply');
        $secret = $this->getRequest()->getHeader('X-Auth-Secret');

        if ( !$helper->safeCompare($consumer->getSecret(), $secret) ) {
            throw new Mage_Oauth_Exception(401, 'OAuth secret key rejected.');
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
            
            $this->_validateConsumer($consumer);

            $token = Mage::getModel('groove_hubshoply/token')
                ->setConsumerId($consumer->getId())
                ->setToken( Mage::helper( 'oauth' )->generateToken() )
                ->setExpires( $token::DAY )
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
        } catch (Mage_Oauth_Exception $error) {
            $this->_sendError($error->getCode(), 'OAuth Exception', $error->getMessage());
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logError($error);

            $this->_sendError(500, 'Server Error', 'Exception thrown while authenticating this request.');
        }
    }

    /**
     * View action.
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
     * A customizable view of queue items.
     *
     * @return void
     */
    public function viewAction()
    {
        try {
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
        } catch (Exception $error) {
            Mage::helper('groove_hubshoply/debug')->logException($error);

            $this->_errorBody(500, 'Failed to process view request', $error->getMessage(), null);
        }
    }

    /**
     * Mark action.
     * 
     * Route: hubshoply/queue/mark/id/:id
     * Route: hubshoply/queue/mark/from/:id/to/:id
     *
     * After processing queue items, send Queue_id (or a range) back
     * This will delete the items from the queue
     *
     * @return void
     */
    public function markAction()
    {
        $req = $this->getRequest();

        $this->_checkAuthorization();

        if(!$this->_isMethod('DELETE'))
        {
            //Returns 405 Method Not Allowed if it is not a DELETE method
            //Sets Allow header to user indicating DELETE is the only allowed method
            $this->_errorBody(405, 'Method Not Allowed',
                'You have attempted to access this URI using the ['.$req->getMethod().'] method when DELETE is the only allowed method.',
                function($response) { $response->setHeader('Allow','DELETE',true); }
            );
        }
        elseif($id = $req->getParam('id'))
        {
            $this->_removeFromRange($id,$id);
        }
        elseif(
            ($from = $req->getParam('from'))
            && ($to = $req->getParam('to')))
        {
            $this->_removeFromRange($from,$to);
        }
        else
        {
            //return a 400 error if there is a bad/malformed URL request
            $this->_errorBody(400, 'Bad Request',
                'URI needs to be in the format [hubshoply/mark/id/###] for single deletions '
                .'or [hubshoply/mark/from/###/to/###]. Your URI was ['.$req->getRequestUri().']'
            );
        }
    }

    /**
     * Check for authorization. Halt with a 401 if unauthorized, else return token.
     * 
     * @return Groove_Hubshoply_Model_Token
     */
    private function _checkAuthorization()
    {
        //get Token from header
        $token = $this->getRequest()->getHeader('X-Access-Token');
        //attempt to load the token
        $loaded = Mage::getModel('groove_hubshoply/token')->load($token,'token');
        //test if token doesn't exist or isExpired
        if(!$loaded->getId() || $loaded->isExpired())
        {
            //generate message based on if using a single ID or actual range.
            $message = 'Your token is invalid or not present. Please re-authenticate and try again.';
            $callback = function($response){
                $response->setHeader('WWW-Authenticate','Custom',true);
            };
            //send error body and finish request
            $this->_errorBody(401, 'Unauthorised',$message,$callback);
        }
        else
        {
            return $loaded;
        }
    }
    /**
     * Validate the requested method.
     * 
     * @param string $method Method to check for
     *
     * @return bool Returns TRUE if Request method is the same as the $method argument
     */
    private function _isMethod($method)
    {
        return strcasecmp($this->getRequest()->getMethod(), $method) == 0 ;
    }

    /**
     * Creates a JSON Error message to throw, and sets appropriate response headers.
     * Then sends response and terminates any further controller actions.
     *
     * @param int|string $code          HTTP Error Code
     * @param string $message           HTTP Error Message
     * @param string $details           Details about error
     * @param null|callable $callback   Optional Callback to apply to response
     *
     * @throws Zend_Controller_Response_Exception
     */
    private function _errorBody($code,$message,$details, $callback = null)
    {
        //Response message
        $response = array(
            'error_code' => $code,
            'error_message' => $message,
            'error_details' => $details
        );
        //format as JSON
        $response = json_encode($response);
        //Set code and Response body
        $this->getResponse()->setHttpResponseCode($code);
        $this->getResponse()->setBody($response);
        //Additional response actions if needed
        if(is_callable($callback))
        {
            $callback($this->getResponse());
        }
        //Send response and terminate controller actions
        $this->getResponse()->sendResponse();
        exit;
    }

    /**
     * Creates a JSON Error message to throw, and sets appropriate response headers.
     * Then sends response and terminates any further controller actions.
     *
     * @param int|string $code          HTTP Error Code
     * @param string $message           HTTP Error Message
     * @param string $details           Details about error
     * @param null|callable $callback   Optional Callback to apply to response
     *
     * @return void
     */
    private function _sendError($code,$message,$details, $callback = null)
    {
        //Response message
        $response = array(
            'error_code' => $code,
            'error_message' => $message,
            'error_details' => $details
        );
        //format as JSON
        $response = json_encode($response);
        //Set code and Response body
        $this->getResponse()->setHttpResponseCode($code);
        $this->getResponse()->setBody($response);
        //Additional response actions if needed
        if(is_callable($callback))
        {
            $callback($this->getResponse());
        }
        //Send response and terminate controller actions
        $this->getResponse()->sendResponse();
        exit;
    }

    /**
     * Takes an exception and logs it,
     * as well as terminates controller with a 500 error message
     *  
     * @param Exception $x
     */
    private function _handleException(Exception $x)
    {
        Mage::log(
            $x->getMessage().PHP_EOL.$x->getTraceAsString()
            ,null, $this::QUEUE_CONTROL_LOG
        );
        $this->_errorBody(500, 'Server Error',
            "There was an error processing your request, please check your servers' log files or try again at a later date."
        );
    }

    /**
     * Validate the queue ID range.
     * 
     * @param int $from
     * @param int $to
     *
     * @return bool Returns true if the range is valid numbers
     */
    private function _validateQueueIdRange($from,$to)
    {
        //check if range is valid
        $valid = is_numeric($from)
                 && is_numeric($to)
                 && ($from <= $to);
        //if invalid, return/throw error
        if(!$valid)
        {
            //generate message based on if using a single ID or actual range.
            $message = ($from === $to)
                ?"Queue ID provided [$from] is an invalid integer."
                :"Queue ID range provided [FROM $from, TO $to] is an invalid range of integers.";
            //send error body and finish request
            $this->_errorBody(400, 'Bad Request',$message);
        }
    }

    /**
     * Removes all queue items with ID's in the range ($from,$to) inclusive
     * 
     * @param int $from
     * @param int $to
     *
     * @return void
     */
    private function _removeFromRange($from,$to)
    {
        //validate range. If invalid, it terminates before continuing further down.
        $this->_validateQueueIdRange($from,$to);
        try
        {
            //delete item if present. If item doesn't exist, it is pretty much already deleted
            Mage::getModel('groove_hubshoply/queueitem')
                ->getCollection()
                ->addFieldToFilter('queue_id', array('from'=>$from,'to'=>$to))
                ->walk('delete');
        }
        catch(Exception $x)
        {
            $this->_handleException($x);
        }
    }

    /**
     * Calls our helper that queues up abandoned carts
     *
     * @return void
     */
    private function _queueCarts()
    {
        Mage::getModel('groove_hubshoply/event')->abandonCartProcessing(new Varien_Event_Observer());
    }
    
}