<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\Tier;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Account\CollectionFactory as AccountCollectionFactory;

/**
 * UI DataProvider for Customers in a Tier
 */
class CustomerDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param AccountCollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AccountCollectionFactory $collectionFactory,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data filtered by tier_id from request
     *
     * @return array
     */
    public function getData(): array
    {
        $tierId = $this->request->getParam('tier_id');

        if ($tierId) {
            $this->collection->addFieldToFilter('current_tier_id', (int) $tierId);
        }

        $this->collection->getSelect()->joinLeft(
            ['ce' => $this->collection->getResource()->getConnection()->getTableName('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            [
                'customer_email' => 'ce.email',
                'customer_firstname' => 'ce.firstname',
                'customer_lastname' => 'ce.lastname',
            ],
        );

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
