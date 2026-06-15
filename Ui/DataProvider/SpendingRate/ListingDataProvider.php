<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\SpendingRate;

use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate\CollectionFactory;

/**
 * Spending Rate Listing Data Provider
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        $result = parent::getData();
        $connection = $this->resourceConnection->getConnection();

        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');
        $storeWebsiteTable = $this->resourceConnection->getTableName('store_website');
        $customerGroupTable = $this->resourceConnection->getTableName('customer_group');

        foreach ($result['items'] as &$item) {
            $rateId = (int) $item['rate_id'];

            $websiteSelect = $connection->select()
                ->from(['rw' => $websiteTable], [])
                ->join(['sw' => $storeWebsiteTable], 'rw.website_id = sw.website_id', ['name'])
                ->where('rw.rule_id = ?', $rateId)
                ->where('rw.rule_type = ?', 'spending_rate');

            $websiteNames = $connection->fetchCol($websiteSelect);
            $item['websites'] = implode(', ', $websiteNames);

            $cgSelect = $connection->select()
                ->from(['rcg' => $cgTable], [])
                ->join(['cg' => $customerGroupTable], 'rcg.customer_group_id = cg.customer_group_id', ['customer_group_code'])
                ->where('rcg.rule_id = ?', $rateId)
                ->where('rcg.rule_type = ?', 'spending_rate');

            $groupNames = $connection->fetchCol($cgSelect);
            $item['customer_groups'] = implode(', ', $groupNames);
        }

        return $result;
    }
}
