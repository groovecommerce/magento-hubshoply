<?php

/**
 * HubShop.ly Magento
 * 
 * Event model.
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
 * @category Class_Type_Model
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Model_Event
{

    const EVENT_CONTROL_LOG = 'hubshoply_event_observer.log';
    //Host for Hubshoply App where the script embed comes from
    const SCRIPT_HOST = '//magento.hubshop.ly/shops/';

    /**
     * Inser the event JavaScript.
     * 
     * @param Varien_Event_Observer $observer The event details.
     * 
     * @return void
     */
    public function insertJavascript(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        if($block->getType() === 'page/html_head')
        {
            //get domain hash
            $domain_hash = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $domain_hash = basename($domain_hash);
            $domain_hash = md5($domain_hash);
            //construct the <script> embed
            $script_tag = sprintf(
                '<script type="text/javascript" src="%s"></script>',
                $this::SCRIPT_HOST.$domain_hash.'.js');
            //construct email capturing script if customer is logged in
            $email_script = "";
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $email = Mage::getSingleton('customer/session')
                    ->getCustomer()
                    ->getEmail();
                $email_script = sprintf('<script type="text/javascript">window.Hubshoply = {}; Hubshoply.customerEmail = "%s";</script>',$email);
            }
            // Append new scripts to <head> tag.
            $transport = $observer->getTransport();
            $transport->setHtml(
                $transport->getHtml()
                .$script_tag
                .$email_script
            );
        }
    }

    /**
     * Adds a registered customer to the queue
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function createAccount(Varien_Event_Observer $observer)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        $group = Mage::getModel('customer/group')->load($customer->getGroupId());
        $this->_addToQueue('customer','create',
                array(
                    'email' => $customer->getEmail(),
                    'account_creation_date' => $customer->getCreatedAtTimestamp(),
                    'first_name' => $customer->getFirstname(),
                    'last_name' => $customer->getLastname(),
                    'middle_name_initial' => $customer->getMiddlename(),
                    'customer_group' => $group->getCode(),
                ),
            $customer->getStore()->getId());
    }

    /**
     * Adds a newsletter subscriber to the queue
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function createNewsletterAccount(Varien_Event_Observer $observer)
    {
        //if they updated from subscribed to unsubscriber and vice versa
        $changed = $observer->getEvent()->getDataObject()->getIsStatusChanged();
        //what their new subscriber status is
        $subscribed = $observer->getEvent()->getDataObject()->getStatus();
        //if their status changed and they are now a subscriber (as opposed to unsubscriber)
        //add their details to the queue
        if($changed && ($subscribed == 1) )
        {
            $subscriber = $observer->getEvent()->getDataObject()->getSubscriberEmail();
            $this->_addToQueue('newsletter','subscribe',
                array(
                    'email' => $subscriber,
                    'subscription_date' => time(),
                ));
        }
    }

    /**
     * Adds a customer review to the queue
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function createReview(Varien_Event_Observer $observer)
    {
        //get review object data
        $reviewData = $observer->getEvent()->getDataObject()->getData();
        //format for JSON payload
        $payload = array(
            'review_id' => $reviewData['review_id'],
            'created_at' => $reviewData['created_at'],
            'product_id' => $reviewData['entity_id'],
            'customer_id' => $reviewData['customer_id'], //can be null
            'review_title' => $reviewData['title'],
            'review_detail' => $reviewData['detail'],
            'customer_nickname' => $reviewData['nickname'],
            'product_url_suffix' => Mage::helper('catalog/product')->getProductUrlSuffix(),
        );
        //add review to queue
        $this->_addToQueue('review','create',$payload,$reviewData['store_id']);
    }

    /**
     * Queues up Abandoned carts
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function abandonCartProcessing(Varien_Event_Observer $observer)
    {
        //get collection of non-queued abandoned carts
        $carts = Mage::getModel('groove_hubshoply/abandonedcart')
            ->getCollection()
            ->addFieldToFilter('enqueued',false);
        //add carts to queue
        $carts->walk(array($this,'_queueUpCarts'));
    }

    /**
     * Takes an abandoned cart, adds it to the queue, then flags it as queued
     * 
     * @param Groove_Hubshoply_Model_Abandonedcart $cart
     *
     * @return void
     */
    public function _queueUpCarts(Groove_Hubshoply_Model_Abandonedcart $cart)
    {
        //try to add cart to queue
        $enqueue = $this->_addToQueue('cart','abandoned',$cart->getPayload(),$cart->getStoreId());
        //if queued up successfully, set flag so it's not re-queued
        if($enqueue)
        {
            $cart->setEnqueued(true)->save();
        }
    }

    /**
     * Tracks order before-save events. Created and Updated
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function saveOrderBefore(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getDataObject();
        if($order->isObjectNew())
        {
            $order->setObjectNewTmp(true);
        }
    }

    /**
     * Tracks order after-save events. Created and Updated
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function saveOrderAfter(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getDataObject();
        //default payload with Order ID
        $payload = array(
            'order_id' => $order->getId(),
            'product_url_suffix' => Mage::helper('catalog/product')->getProductUrlSuffix(),
        );
        //add to queue as updated or created, with appropriate timestamps
        if($order->getObjectNewTmp())
        {
            $payload = $payload + array('created_at' => date(DateTime::W3C,strtotime($order->getCreatedAt())));
            $this->_addToQueue('order','created',$payload,$order->getStoreId());
            $order->setObjectNewTmp(false);
        }
        else
        {
            //if created and updated timestamps are the same, this is the double-save from an order
                //and not really an update as we want it
            //still an occasional, but acceptable, room for error
                //if the timestamps are a second or two apart between saves
            if($order->getCreatedAt() != $order->getUpdatedAt() )
            {
                $payload = $payload + array('updated_at' => date(DateTime::W3C,strtotime($order->getUpdatedAt())));
                $this->_addToQueue('order','updated',$payload,$order->getStoreId());
            }
        }
    }

    /**
     * Add a shipment record to the queue.
     * 
     * @param Varien_Event_Observer $observer The event details.
     * 
     * @return void
     */
    public function saveShipment(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Shipment $shipment */
        $shipment = $observer->getEvent()->getDataObject();
        $email = $shipment->getOrder()->getCustomerEmail();
        $tracking = $shipment->getAllTracks();
        $trackingArray = $trackingCarrierArray = array();
        foreach($tracking as $track)
        {
            $trackingArray[] = $track->getNumber();
            $trackingCarrierArray[] = $track->getTitle();
        }
        $payload = array(
            'email' => $email,
            'created_at' => date(DateTime::W3C,strtotime($shipment->getCreatedAt())),
            'tracking_number' => $trackingArray,
            'tracking_carrier' => $trackingCarrierArray,
            'product_url_suffix' => Mage::helper('catalog/product')->getProductUrlSuffix(),
        );
        $this->_addToQueue('shipment','created',$payload,$shipment->getStoreId());
    }

    /**
     * Adds an updated customer to the queue
     * 
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function updateCustomer(Varien_Event_Observer $observer)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        $group = Mage::getModel('customer/group')->load($customer->getGroupId());
        //only new customers are "updated" otherwise they are caught by the customer registration observer
        if(!$customer->isObjectNew())
        {
            $this->_addToQueue( 'customer', 'updated',
                array(
                    'customer_id' => $customer->getId(),
                    'email'       => $customer->getEmail(),
                    'first_name' => $customer->getFirstname(),
                    'last_name' => $customer->getLastname(),
                    'middle_name_initial' => $customer->getMiddlename(),
                    'account_creation_date' => $customer->getCreatedAtTimestamp(),
                    'customer_group' => $group->getCode(),
                    'updated_at'  => date( DateTime::W3C, strtotime( $customer->getUpdatedAt() ) )
                ),
                $customer->getStore()->getId() );
        }
    }

    /**     
     * Enqueues event to database.
     * 
     * @param string $entity
     * @param string $event
     * @param string $data
     * @param int|string $store_id
     * 
     * @return bool If successfully added to queue
     */
    private function _addToQueue($entity,$event, $data,$store_id = null)
    {
        //get store ID, or use provided
        $store_id = is_null($store_id)?Mage::app()->getStore()->getId():$store_id;
        //save item to queue, or log error
        try{
            $queueitem = Mage::getModel('groove_hubshoply/queueitem')
                    ->setEventType($event)
                    ->setEventEntity($entity)
                    ->setStoreId($store_id)
                    ->setPayload(json_encode($data));
            $queueitem->save();
            return true;
        }
        catch(Exception $x)
        {
            Mage::log(
                        sprintf('Attempted to add to queue: Entity: [%s] Event: [%s] Store [%s], Payload [%s]'
                                    ,$entity,$event,$store_id,$data)
                        .PHP_EOL.$x->getMessage()
                        .PHP_EOL.$x->getTraceAsString()
                ,null,$this::EVENT_CONTROL_LOG
            );
            return false;
        }
    }
    
}