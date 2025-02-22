<?php

namespace Dotdigitalgroup\Email\Model\ResourceModel\Contact;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'email_contact_id';

    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * Initialize resource collection.
     *
     * @return null
     */
    public function _construct()
    {
        $this->_init(
            \Dotdigitalgroup\Email\Model\Contact::class,
            \Dotdigitalgroup\Email\Model\ResourceModel\Contact::class
        );
    }

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->subscriberFactory = $subscriberFactory;
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
     * Load contact by customer id.
     *
     * @param int $customerId
     *
     * @return bool|\Dotdigitalgroup\Email\Model\Contact
     */
    public function loadByCustomerId($customerId)
    {
        $collection = $this->addFieldToFilter('customer_id', $customerId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Load contact by customer id and website id.
     *
     * @param string|int $customerId
     * @param string|int $websiteId
     * @return bool|\Dotdigitalgroup\Email\Model\Contact
     */
    public function loadByCustomerIdAndWebsiteId($customerId, $websiteId)
    {
        $collection = $this->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('website_id', $websiteId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Get all customer contacts not imported for a website.
     *
     * @param int $websiteId
     * @param int $pageSize
     *
     * @return $this
     */
    public function getContactsToImportForWebsite($websiteId, $pageSize = 100)
    {
        $collection = $this->addFieldToFilter('website_id', $websiteId)
            ->addFieldToFilter('email_imported', 0)
            ->addFieldToFilter('customer_id', ['neq' => '0']);

        $collection->getSelect()->limit($pageSize);

        return $collection;
    }

    /**
     * Get missing contacts.
     *
     * @param int $websiteId
     * @param int $pageSize
     *
     * @return $this
     */
    public function getMissingContacts($websiteId, $pageSize = 100)
    {
        $collection = $this->addFieldToFilter('contact_id', ['null' => true])
            ->addFieldToFilter('suppressed', ['null' => true])
            ->addFieldToFilter('website_id', $websiteId);

        $collection->getSelect()->limit($pageSize);

        return $collection->load();
    }

    /**
     * Load Contact by Email.
     *
     * @param string $email
     * @param int $websiteId
     *
     * @return bool|\Dotdigitalgroup\Email\Model\Contact
     */
    public function loadByCustomerEmail($email, $websiteId)
    {
        $collection = $this->addFieldToFilter('email', $email)
            ->addFieldToFilter('website_id', $websiteId)
            ->setPageSize(1);

        if ($collection->getSize()) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Load all customers matching the supplied customer id.
     *
     * @param string $id
     * @return $this
     */
    public function loadCustomersById($id)
    {
        return $this->addFieldToFilter('customer_id', $id);
    }

    /**
     * Contact subscribers to import for store.
     *
     * @param int $storeId
     * @param int $limit
     * @param bool $isCustomerCheck
     * @return $this
     */
    public function getSubscribersToImport(
        $storeId,
        $limit = 1000,
        $isCustomerCheck = true
    ) {
        $collection = $this->addFieldToFilter('is_subscriber', ['notnull' => true])
            ->addFieldToFilter('subscriber_status', '1')
            ->addFieldToFilter('subscriber_imported', 0)
            ->addFieldToFilter('store_id', ['eq' => $storeId]);

        if ($isCustomerCheck) {
            $collection->addFieldToFilter('customer_id', ['neq' => 0]);
        } else {
            $collection->addFieldToFilter('customer_id', ['eq' => 0]);
        }

        $collection->getSelect()->limit($limit);

        return $collection;
    }

    /**
     * Fetch a list of subscribers by email and store id.
     *
     * @param array $emails
     * @param string|int $storeId
     * @return $this
     */
    public function getSubscribersToImportFromEmails($emails, $storeId)
    {
        return $this->addFieldToFilter('email', ['in' => $emails])
            ->addFieldToFilter('store_id', $storeId);
    }

    /**
     * Get all not imported guests for a website.
     *
     * @param int $websiteId
     * @param boolean $onlySubscriber
     *
     * @return $this
     */
    public function getGuests($websiteId, $onlySubscriber = false)
    {
        $guestCollection = $this->addFieldToFilter('is_guest', ['notnull' => true])
            ->addFieldToFilter('email_imported', 0)
            ->addFieldToFilter('website_id', $websiteId);

        if ($onlySubscriber) {
            $guestCollection->addFieldToFilter('is_subscriber', 1)
                ->addFieldToFilter(
                    'subscriber_status',
                    \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
                );
        }

        return $guestCollection->load();
    }

    /**
     * Number contacts marked as imported.
     *
     * @return int
     */
    public function getNumberOfImportedContacts()
    {
        $this->addFieldToFilter('email_imported', 1);

        return $this->getSize();
    }

    /**
     * Get the number of customers for a website.
     *
     * @param int $websiteId
     *
     * @return int
     */
    public function getNumberCustomerContacts($websiteId = 0)
    {
        return $this->addFieldToFilter('customer_id', ['gt' => '0'])
            ->addFieldToFilter('website_id', $websiteId)
            ->getSize();
    }

    /**
     * Get number of suppressed contacts as customer.
     *
     * @param int $websiteId
     *
     * @return int
     */
    public function getNumberCustomerSuppressed($websiteId = 0)
    {
        return $this->addFieldToFilter('customer_id', ['gt' => 0])
            ->addFieldToFilter('website_id', $websiteId)
            ->addFieldToFilter('suppressed', '1')
            ->getSize();
    }

    /**
     * Get number of synced customers.
     *
     * @param int $websiteId
     *
     * @return int
     */
    public function getNumberCustomerSynced($websiteId = 0)
    {
        return $this->addFieldToFilter('customer_id', ['gt' => 0])
            ->addFieldToFilter('website_id', $websiteId)
            ->addFieldToFilter('email_imported', 1)
            ->getSize();
    }

    /**
     * Get number of subscribers synced.
     *
     * @param int $websiteId
     *
     * @return int
     */
    public function getNumberSubscribersSynced($websiteId = 0)
    {
        return $this->addFieldToFilter(
            'subscriber_status',
            \Dotdigitalgroup\Email\Model\Newsletter\Subscriber::STATUS_SUBSCRIBED
        )
            ->addFieldToFilter('subscriber_imported', 1)
            ->addFieldToFilter('website_id', $websiteId)
            ->getSize();
    }

    /**
     * Get number of subscribers.
     *
     * @param int $websiteId
     *
     * @return int
     */
    public function getNumberSubscribers($websiteId = 0)
    {
        return $this->addFieldToFilter(
            'subscriber_status',
            \Dotdigitalgroup\Email\Model\Newsletter\Subscriber::STATUS_SUBSCRIBED
        )
            ->addFieldToFilter('website_id', $websiteId)
            ->getSize();
    }

    /**
     * Get subscribers data by emails
     *
     * @param array $emails
     * @return array
     */
    public function getSubscriberDataByEmails($emails)
    {
        $subscriberFactory = $this->subscriberFactory->create();
        $subscribersData = $subscriberFactory->getCollection()
            ->addFieldToFilter(
                'subscriber_email',
                ['in' => $emails]
            )
            ->addFieldToSelect(['subscriber_email', 'store_id']);

        return $subscribersData->toArray();
    }

    /**
     * Get contacts to import by website
     *
     * @param int $websiteId
     * @param int $syncLimit
     * @param boolean $onlySubscriber
     * @return $this
     */
    public function getContactsToImportByWebsite($websiteId, $syncLimit, $onlySubscriber = false)
    {
        $collection = $this->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', ['neq' => '0'])
            ->addFieldToFilter('email_imported', 0)
            ->addFieldToFilter('website_id', $websiteId)
            ->setPageSize($syncLimit);

        if ($onlySubscriber) {
            $collection->addFieldToFilter('is_subscriber', 1)
                ->addFieldToFilter(
                    'subscriber_status',
                    \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
                );
        }

        return $collection;
    }

    /**
     * @param array $customerIds
     * @param string|int $websiteId
     * @return Collection
     */
    public function getCustomerScopeData(array $customerIds, $websiteId = 0)
    {
        return $this->addFieldToFilter('customer_id', ['in' => $customerIds])
            ->addFieldToFilter('website_id', $websiteId)
            ->addFieldToSelect(['customer_id', 'store_id']);
    }

    /**
     * @param array $emails
     * @param string|int $websiteId
     * @return $this
     */
    public function getContactsByEmailsAndWebsiteId($emails, $websiteId)
    {
        return $this->addFieldToFilter('email', ['in' => $emails])
            ->addFieldToFilter('website_id', $websiteId);
    }

    /**
     * Get current subscribed contact records to check when they last subscribed.
     *
     * @param array $emails
     * @param array $websiteIds
     *
     * @return array
     */
    public function getSubscribersWithScopeAndLastSubscribedAtDate(array $emails, $websiteIds)
    {
        return $this
            ->addFieldToSelect([
                'email',
                'last_subscribed_at',
                'website_id',
                'store_id'
            ])
            ->addFieldToFilter('email', ['in' => $emails])
            ->addFieldToFilter('website_id', ['in' => $websiteIds])
            ->addFieldToFilter('is_subscriber', 1)
            ->addFieldToFilter(
                'subscriber_status',
                \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
            )
            ->getData();
    }

    /**
     * Find matching contacts.
     *
     * @param array $emails
     * @param int $websiteId
     *
     * @return array
     */
    public function matchEmailsToContacts($emails, $websiteId)
    {
        return $this->addFieldToFilter('email', ['in' => $emails])
            ->addFieldToFilter('website_id', $websiteId)
            ->getColumnValues('email');
    }
}
