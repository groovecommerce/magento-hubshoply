<?php

/**
 * HubShop.ly Magento
 * 
 * Queue controller.
 * 
 * @category  Class
 * @package   Groove_Hubshoply
 * @author    Groove Commerce
 * @copyright 2016 Groove Commerce, LLC. All Rights Reserved.
 *
 * LICENSE
 * 
 * The MIT License (MIT)
 * Copyright (c) 2016 Groove Commerce, LLC.
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

    const QUEUE_CONTROL_LOG = 'hubshoply_queue_controller.log';

    /**
     * Queue authenticate action.
     * 
     * Requires
     *  X-Auth-Key:    OAuth Consumer Key
     *  X-Auth-Secret: Oauth Consumer Secret
     * 
     * Returns
     *  Body: JSON Message including an Access Token with Expiration time
     *  X-Access-Token:   The access token
     *  X-Access-Expires: The expiration time of access token
     *
     * @return void
     */
    public function authenticateAction()
    {
        //Get HTTP Authentication Headers
        list($key, $secret) = array(
            $this->getRequest()->getHeader('X-Auth-Key'),
            $this->getRequest()->getHeader('X-Auth-Secret'),
        );
        //Load possible consumer
        $consumer = Mage::getModel('oauth/consumer')->load($key,'key');
        //Test if consumer with key exists
        //avoid short circuit for constant-time regardless of existence or not
        $allow = true && $consumer->getId();
        //Test if secret is valid && the previous result
        $allow = Mage::helper('groove_hubshoply')
                    ->safeCompare($consumer->getSecret(), $secret)
                 && $allow;
        if($allow)
        {
            $token = Mage::getModel('groove_hubshoply/token');
            try
            {
                //generate new token for consumer
                $token->setConsumerId( $consumer->getId() )
                      ->setToken( Mage::helper( 'oauth' )->generateToken() )
                      ->setExpires( $token::DAY )
                      ->save();
                //generate JSON response
                $response = json_encode(array(
                    'token'   => $token->getToken(),
                    'expires' => $token->getExpires(),
                ));
                //set custom header with token, as well as returning the token response information
                $this->getResponse()
                    ->setHeader('X-Access-Token',$token->getToken())
                    ->setHeader('X-Access-Expires',$token->getExpires())
                    ->setBody($response)
                    ->sendResponse();
                exit;
            }
            catch(Exception $x)
            {
                $this->_handleException($x);
            }
        }
        else
        {
            $this->_errorBody(401, 'Unauthorized', 'Invalid Client or Token provided.');
        }
    }

    /**
     * Index action.
     * 
     * Gives details into the routes.
     * Can/will return the expiration date of current token.
     *
     * @return void
     */
    public function indexAction()
    {
        $param = $this->getRequest()->isSecure()?array('_forced_secure'=>true):null;
        $token = $this->_checkAuthorization();
        $data = json_encode(array(
            'message'=>'Hey, you\'re authorized! This endpoint has no function,'
                       .' but feel free to utilize the alternate routes.'
                       .' Access Token required on all routes.',
            'token_lifetime' => 'Your current token will expire: '.$token->getExpires(),
            'authenticate' => array(
                'route' => Mage::getUrl('hubshoply/queue/authenticate',$param),
                'options' => array(),
                'required_headers' => array('X-Auth-Client','X-Auth-Key'),
            ),
            'view_queue' => array(
                'route' => Mage::getUrl('hubshoply/queue/view',$param),
                'options' => array('first' => ':int','last' => ':int','limit' => ':offset,:count','entity' => ':string','type' => ':string','store' => ':int' ),
                'required_headers' => array('X-Access-Token'),
                ),
            'delete_queue' => array(
                'route' => Mage::getUrl('hubshoply/queue/mark',$param),
                'options' => array('id'=>':int', 'from'=>':int','to'=>':int'),
                'required_headers' => array('X-Access-Token'),
            ),
        ));
        $this->getResponse()
            ->setBody($data)
            ->sendResponse();
        exit;
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
     * Most Routes can be compounded
     * Ex. hubshoply/queue/view/first/:count/store/:store_id/type/:type
     *
     * FIRST, LAST, and LIMIT are exclusive and cannot be compounded
     *
     * A customizable view of queue items
     *
     * @return void
     */
    public function viewAction()
    {
        $this->_checkAuthorization();
        $req = $this->getRequest();
        //get base collection
        $collection = Mage::getModel('groove_hubshoply/queueitem')
            ->getCollection();
        //get FIRST COUNT, LAST COUNT, or LIMIT OFFSET,COUNT items
        if($x = $req->getParam('first'))
        {
            $collection->setOrder('created_at',$collection::SORT_ORDER_ASC);
            $collection->getSelect()->limit($x);
        }
        elseif($x = $req->getParam('last'))
        {
            $collection->setOrder('created_at',$collection::SORT_ORDER_DESC);
            $collection->getSelect()->limit($x);
        }
        elseif($limit = $req->getParam('limit'))
        {
            //SQL-STYLE LIMIT filtering
            // LIMIT offset,count
            $limit = explode(',',$limit);
            $collection->setOrder('created_at',$collection::SORT_ORDER_ASC);
            $collection->getSelect()->limit($limit[1],$limit[0]);
        }
        //Filter by TYPE, ENTITY, or STORE
        if($type = $req->getParam('type') )
        {
            $collection->addFieldToFilter('event_type',$type);
        }
        if($entity = $req->getParam('entity') )
        {
            $collection->addFieldToFilter('event_entity',$entity);
        }
        if($store = $req->getParam('store') )
        {
            $collection->addFieldToFilter('store_id',$store);
        }
        //queue abandon cart items on the fly before finally loading the queue item collection
        //up until now the collection was being defined/built, but not loaded.
        $this->_queueCarts();
        //return JSON collection
        $this->getResponse()->setBody($collection->getQueueCollectionJson())->sendResponse();
        exit;
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
     * @return void
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
        Mage::getModel('groove_hubshoply/event')->abandonCartProcessing(null);
    }
    
}