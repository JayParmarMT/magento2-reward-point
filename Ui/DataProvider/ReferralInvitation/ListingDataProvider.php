<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\ReferralInvitation;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation\CollectionFactory;

/**
 * UI DataProvider for Referral Invitation Listing
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->joinReferrerEmail();
    }

    /**
     * Join customer entity to get referrer email
     *
     * @return void
     */
    private function joinReferrerEmail(): void
    {
        $this->collection->getSelect()->joinLeft(
            ['ce' => $this->collection->getResource()->getConnection()->getTableName('customer_entity')],
            'main_table.referrer_customer_id = ce.entity_id',
            ['referrer_email' => 'ce.email'],
        );
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = $this->getCollection()->toArray();

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($items['items'] ?? $items),
        ];
    }
}
