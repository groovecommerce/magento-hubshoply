<?php

/**
 * HubShop.ly Magento
 * 
 * Event model.
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

class Groove_Hubshoply_Model_Event
{

    const EVENT_CONTROL_LOG = 'hubshoply_event_observer.log';

    /* @var $_activeStores array */
    private $_activeStores;

    /* @var $_debug Groove_Hubshoply_Helper_Debug */
    private $_debug;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_debug           = Mage::helper('groove_hubshoply/debug');
        $this->_activeStores    = Mage::getSingleton('groove_hubshoply/config')->getActiveStores();
    }

    /**
     * Determine whether the event observed is actionable.
     * 
     * @param Varien_Event_Observer $observer The event details.
     * @param integer               $storeId  Optional store ID for context.
     * 
     * @return boolean
     */
    private function _canObserve(Varien_Event_Observer $observer, $storeId = null)
    {
        if (empty($this->_activeStores)) {
            return false;
        }

        if (!is_numeric($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        return empty($this->_activeStores[$storeId]) ? false : true;
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
        if (!$this->_canObserve($observer)) {
            return;
        }

        /* @var Mage_Customer_Model_Customer $customer */
        $customer   = $observer->getEvent()->getCustomer();
        $group      = Mage::getModel('customer/group')->load($customer->getGroupId());

        $this->_addToQueue(
            'customer',
            'create',
            array(
                'email'                 => $customer->getEmail(),
                'account_creation_date' => $customer->getCreatedAtTimestamp(),
                'first_name'            => $customer->getFirstname(),
                'last_name'             => $customer->getLastname(),
                'middle_name_initial'   => $customer->getMiddlename(),
                'customer_group'        => $group->getCode(),
            ),
            $customer->getStore()->getId()
        );

        $this->_debug->log(sprintf('Event queued: customer.create(%d).', $customer->getId()), Zend_Log::DEBUG);
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
        if (!$this->_canObserve($observer)) {
            return;
        }

        $changed    = $observer->getEvent()->getDataObject()->getIsStatusChanged();
        $subscribed = $observer->getEvent()->getDataObject()->getStatus();

        if ( $changed && ($subscribed == 1) ) {
            $subscriber = $observer->getEvent()->getDataObject()->getSubscriberEmail();

            $this->_addToQueue(
                'newsletter',
                'subscribe',
                array(
                    'email'             => $subscriber,
                    'subscription_date' => time(),
                )
            );

            $this->_debug->log(sprintf('Event queued: newsletter.subscribe(%s).', $subscriber), Zend_Log::DEBUG);
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
        $review = $observer->getEvent()->getDataObject();

        if (!$this->_canObserve($observer, current($review->getStores()))) {
            return;
        }

        $this->_addToQueue(
            'review',
            'create',
            array(
                'review_id'             => $review->getId(),
                'created_at'            => $review->getCreatedAt(),
                'product_id'            => $review->getEntityId(),
                'customer_id'           => $review->getCustomerId(), // can be null
                'review_title'          => $review->getTitle(),
                'review_detail'         => $review->getDetail(),
                'customer_nickname'     => $review->getNickname(),
                'product_url_suffix'    => Mage::helper('catalog/product')->getProductUrlSuffix(),
            ),
            $review->getStoreId()
        );

        $this->_debug->log(sprintf('Event queued: review.create(%d).', $review->getId()), Zend_Log::DEBUG);
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
        if (!$this->_canObserve($observer)) {
            return;
        }

        $carts = Mage::getModel('groove_hubshoply/abandonedcart')
            ->getCollection()
            ->addFieldToFilter('enqueued', false);

        $total = $carts->getSize();

        $carts->walk(array($this,'_queueUpCarts'));

        $this->_debug->log(sprintf('Queued %d abandoned carts.', $total), Zend_Log::DEBUG);
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
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getDataObject();

        if (!$this->_canObserve($observer, $order->getStoreId())) {
            return;
        }

        if ($order->isObjectNew()) {
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
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getDataObject();

        if (!$this->_canObserve($observer, $order->getStoreId())) {
            return;
        }

        $payload = array(
            'order_id'              => $order->getId(),
            'product_url_suffix'    => Mage::helper('catalog/product')->getProductUrlSuffix(),
        );

        if ($order->getObjectNewTmp()) {
            $payload = $payload + array('created_at' => date(DateTime::W3C, strtotime($order->getCreatedAt())));
            
            $this->_addToQueue('order', 'created', $payload, $order->getStoreId());

            $this->_debug->log(sprintf('Event queued: order.created(%d).', $order->getId()), Zend_Log::DEBUG);

            $order->setObjectNewTmp(false);
        } else {
            if ( $order->getCreatedAt() != $order->getUpdatedAt() ) {
                $payload = $payload + array('updated_at' => date(DateTime::W3C, strtotime($order->getUpdatedAt())));

                $this->_addToQueue('order', 'updated', $payload, $order->getStoreId());

                $this->_debug->log(sprintf('Event queued: order.updated(%d).', $order->getId()), Zend_Log::DEBUG);
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
        /* @var Mage_Sales_Model_Order_Shipment $shipment */
        $shipment   = $observer->getEvent()->getDataObject();

        if (!$this->_canObserve($observer, $shipment->getStoreId())) {
            return;
        }

        $email      = $shipment->getOrder()->getCustomerEmail();
        $tracking   = $shipment->getAllTracks();
        $tracks     = array();
        $carriers   = array();

        foreach ($tracking as $track) {
            $tracks[]   = $track->getNumber();
            $carriers[] = $track->getTitle();
        }

        $this->_addToQueue(
            'shipment',
            'created',
            array(
                'email'                 => $email,
                'created_at'            => date(DateTime::W3C, strtotime($shipment->getCreatedAt())),
                'tracking_number'       => $tracks,
                'tracking_carrier'      => $carriers,
                'product_url_suffix'    => Mage::helper('catalog/product')->getProductUrlSuffix(),
            ),
            $shipment->getStoreId()
        );

        $this->_debug->log(sprintf('Event queued: shipment.created(%d).', $shipment->getId()), Zend_Log::DEBUG);
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
        if (!$this->_canObserve($observer)) {
            return;
        }

        /* @var Mage_Customer_Model_Customer $customer */
        $customer   = $observer->getEvent()->getCustomer();
        $group      = Mage::getModel('customer/group')->load($customer->getGroupId());

        if (!$customer->isObjectNew()) {
            $this->_addToQueue(
                'customer',
                'updated',
                array(
                    'customer_id'           => $customer->getId(),
                    'email'                 => $customer->getEmail(),
                    'first_name'            => $customer->getFirstname(),
                    'last_name'             => $customer->getLastname(),
                    'middle_name_initial'   => $customer->getMiddlename(),
                    'account_creation_date' => $customer->getCreatedAtTimestamp(),
                    'customer_group'        => $group->getCode(),
                    'updated_at'            => date(DateTime::W3C, strtotime($customer->getUpdatedAt())),
                ),
                $customer->getStore()->getId()
            );

            $this->_debug->log(sprintf('Event queued: customer.updated(%d).', $customer->getId()), Zend_Log::DEBUG);
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
    private function _addToQueue($entity, $event, $data, $store_id = null)
    {
        //get store ID, or use provided
        $store_id = is_null($store_id) ? Mage::app()->getStore()->getId() : $store_id;
        //save item to queue, or log error
        try {
            $queueitem = Mage::getModel('groove_hubshoply/queueitem')
                    ->setEventType($event)
                    ->setEventEntity($entity)
                    ->setStoreId($store_id)
                    ->setPayload(json_encode($data));
            $queueitem->save();
            return true;
        } catch (Exception $error) {
            Mage::log(
                sprintf(
                    'Attempted to add to queue: Entity: [%s] Event: [%s] Store [%s], Payload [%s]',
                    $entity,
                    $event,
                    $store_id,
                    $data
                ) . PHP_EOL . $error->getMessage() . PHP_EOL . $error->getTraceAsString(),
                null,
                $this::EVENT_CONTROL_LOG
            );
            return false;
        }
    }

    /**
     * Load the current order into the registry for tracking use.
     *
     * - Does not support multishipping scenarios.
     * 
     * @param Varien_Event_Observer $observer The event details.
     * 
     * @return void
     */
    public function registerOrderForTracking(Varien_Event_Observer $observer)
    {
        if (!$this->_canObserve($observer)) {
            return;
        }

        try {
            $order      = Mage::getModel('sales/order');
            $orderId    = current( ( (array) $observer->getEvent()->getOrderIds() ) );

            $order->load($orderId);

            if ( $order->getId() > 0 && !Mage::registry('current_order') ) {
                Mage::register('current_order', $order);
            }
        } catch (Exception $error) {
            Mage::logException($error);
        }
    }
    
}