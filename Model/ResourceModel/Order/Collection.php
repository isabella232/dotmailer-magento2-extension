<?php

namespace Dotdigitalgroup\Email\Model\ResourceModel\Order;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var \Dotdigitalgroup\Email\Model\Newsletter\SubscriberFilterer
     */
    private $subscriberFilterer;

    /**
     * @var string
     */
    protected $_idFieldName = 'email_order_id';

    /**
     * @var \Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory
     */
    private $orderCollection;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory
     */
    private $quoteCollection;

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;

    /**
     * Initialize resource collection.
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            \Dotdigitalgroup\Email\Model\Order::class,
            \Dotdigitalgroup\Email\Model\ResourceModel\Order::class
        );
    }

    /**
     * Collection constructor.
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollection
     * @param \Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory $orderCollection
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Dotdigitalgroup\Email\Model\Newsletter\SubscriberFilterer $subscriberFilterer
     * @param \Magento\Framework\DB\Adapter\AdapterInterface|null $connection
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb|null $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollection,
        \Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory $orderCollection,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberFilterer $subscriberFilterer,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->helper             = $helper;
        $this->quoteCollection    = $quoteCollection;
        $this->orderCollection    = $orderCollection;
        $this->subscriberFilterer  = $subscriberFilterer;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource
        );
    }

    /**
     * Load the email order by quote id.
     *
     * @param int $orderId
     * @param int $quoteId
     *
     * @return boolean|\Dotdigitalgroup\Email\Model\Order
     */
    public function loadByOrderIdAndQuoteId($orderId, $quoteId)
    {
        $collection = $this->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('quote_id', $quoteId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Get connector order.
     *
     * @param int $orderId
     * @param int $quoteId
     * @param int $storeId
     *
     * @return boolean|\Dotdigitalgroup\Email\Model\Order
     */
    public function getEmailOrderRow($orderId, $quoteId, $storeId)
    {
        $collection = $this->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('quote_id', $quoteId)
            ->addFieldToFilter('store_id', $storeId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Return order collection filtered by order ids.
     *
     * @param array $orderIds
     *
     * @return $this
     */
    public function getOrdersFromIds($orderIds)
    {
        return $this->addFieldToFilter('order_id', ['in' => $orderIds]);
    }

    /**
     * Fetch unprocessed orders.
     *
     * @param string $limit
     * @param array $storeIds
     *
     * @return array
     */
    public function getOrdersToProcess($limit, $storeIds)
    {
        $connectorCollection = $this;
        $connectorCollection->addFieldToFilter('processed', '0');
        $connectorCollection->addFieldToFilter('store_id', ['in' => $storeIds]);
        $connectorCollection->getSelect()->limit($limit);
        $connectorCollection->setOrder(
            'order_id',
            'asc'
        );

        //check number of orders
        if ($connectorCollection->getSize()) {
            return $connectorCollection->getColumnValues('order_id');
        }

        return [];
    }

    /**
     * Get sales collection for review.
     *
     * @param string $orderStatusFromConfig
     * @param array $created
     * @param \Magento\Store\Model\Website $website
     * @param array $campaignOrderIds
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getSalesCollectionForReviews(
        $orderStatusFromConfig,
        $created,
        $website,
        $campaignOrderIds = []
    ) {
        $storeIds = $website->getStoreIds();
        $collection = $this->orderCollection->create()
            ->addFieldToFilter(
                'main_table.status',
                $orderStatusFromConfig
            )
            ->addFieldToFilter('main_table.created_at', $created)
            ->addFieldToFilter(
                'main_table.store_id',
                ['in' => $storeIds]
            );

        if (!empty($campaignOrderIds)) {
            $collection->addFieldToFilter(
                'main_table.increment_id',
                ['nin' => $campaignOrderIds]
            );
        }

        if ($this->helper->isOnlySubscribersForReview($website->getWebsiteId())) {
            $collection = $this->subscriberFilterer->filterBySubscribedStatus($collection);
        }

        return $collection;
    }

    /**
     * Get customer last order id.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param array $storeIds
     *
     * @return boolean|\Magento\Sales\Model\Order
     */
    public function getCustomerLastOrderId(\Magento\Customer\Model\Customer $customer, $storeIds)
    {
        $collection = $this->orderCollection->create()
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setPageSize(1)
            ->setOrder('entity_id');

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Get customer last quote id.
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param array $storeIds
     *
     * @return boolean|\Magento\Quote\Model\Quote
     */
    public function getCustomerLastQuoteId(\Magento\Customer\Model\Customer $customer, $storeIds)
    {
        $collection = $this->quoteCollection->create()
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setPageSize(1)
            ->setOrder('entity_id');

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Get store quotes for either guests or customers, excluding inactive and empty.
     *
     * @param int $storeId
     * @param array $updated
     * @param bool $guest
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection
     */
    public function getStoreQuotes($storeId, $updated, $guest = false)
    {
        $salesCollection = $this->getStoreQuotesForGuestsAndCustomers($storeId, $updated);

        if ($guest) {
            $salesCollection->addFieldToFilter('main_table.customer_id', ['null' => true]);
        } else {
            $salesCollection->addFieldToFilter('main_table.customer_id', ['notnull' => true]);
        }

        return $salesCollection;
    }

    /**
     * Get store quotes for both guests and customers, excluding inactive and empty.
     *
     * @param int $storeId
     * @param array $updated
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection
     */
    public function getStoreQuotesForGuestsAndCustomers($storeId, $updated)
    {
        $salesCollection = $this->quoteCollection->create();
        $salesCollection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->addFieldToFilter('customer_email', ['neq' => ''])
            ->addFieldToFilter('main_table.store_id', $storeId)
            ->addFieldToFilter('main_table.updated_at', $updated);

        if ($this->helper->isOnlySubscribersForAC($storeId)) {
            $salesCollection = $this->subscriberFilterer->filterBySubscribedStatus($salesCollection);
        }

        return $salesCollection;
    }

    /**
     * Get store quotes for both guests and customers, excluding inactive and empty.
     *
     * @param int $storeId
     * @param array $updated
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection
     */
    public function getStoreQuotesForAutomationEnrollmentGuestsAndCustomers($storeId, $updated)
    {
        $salesCollection = $this->quoteCollection->create();
        $salesCollection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('customer_email', ['neq' => ''])
            ->addFieldToFilter('main_table.store_id', $storeId)
            ->addFieldToFilter('main_table.updated_at', $updated);

        if ($this->helper->isOnlySubscribersForAC($storeId)) {
            $salesCollection = $this->subscriberFilterer->filterBySubscribedStatus($salesCollection);
        }

        return $salesCollection;
    }

    /**
     * Check emails exist in sales order table.
     *
     * @param array $emails
     *
     * @return array
     */
    public function checkInSales($emails)
    {
        $collection = $this->orderCollection->create()
            ->addFieldToFilter('customer_email', ['in' => $emails]);
        return $collection->getColumnValues('customer_email');
    }

    /**
     * Fetch quotes filtered by quote ids.
     *
     * @param array $quoteIds
     * @param int $storeId
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection|Object
     */
    public function getStoreQuotesFromQuoteIds($quoteIds, $storeId)
    {
        $salesCollection = $this->quoteCollection->create()
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->addFieldToFilter('customer_email', ['neq' => ''])
            ->addFieldToFilter('entity_id', ['in' => $quoteIds]);

        if ($this->helper->isOnlySubscribersForAC($storeId)) {
            $salesCollection = $this->subscriberFilterer->filterBySubscribedStatus($salesCollection);
        }

        return $salesCollection;
    }

    /**
     * Utility method to return all the order ids in a collection.
     *
     * @return array
     */
    public function getAllOrderIds(): array
    {
        $ids = [];
        foreach ($this->getItems() as $item) {
            $ids[] = $item->getOrderId();
        }
        return $ids;
    }

    /**
     * Returns order ids filtered by date.
     *
     * @param int $storeId
     * @param \DateTime $time
     *
     * @return array
     */
    public function getOrderIdsFromRecentUnprocessedOrdersSince($storeId, $time)
    {
        return $this->addFieldToFilter('processed', '0')
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('updated_at', ['gt' => $time])
            ->getColumnValues('order_id');
    }
}
