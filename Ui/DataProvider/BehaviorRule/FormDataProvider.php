<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\BehaviorRule;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule\CollectionFactory;

/**
 * Form data provider for behavior rule edit form
 */
class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param ResourceConnection $resourceConnection
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = [],
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $ruleId = (int) $this->request->getParam('rule_id');

        if (!$ruleId) {
            return $this->loadedData;
        }

        $this->collection->addFieldToFilter('rule_id', $ruleId);
        $rule = $this->collection->getFirstItem();

        if (!$rule->getId()) {
            return $this->loadedData;
        }

        $ruleData = $rule->getData();
        $ruleData['website_ids'] = $this->getRuleWebsiteIds($ruleId);
        $ruleData['customer_group_ids'] = $this->getRuleCustomerGroupIds($ruleId);

        $this->loadedData[$ruleId] = $ruleData;

        return $this->loadedData;
    }

    /**
     * Get website IDs for rule
     *
     * @param int $ruleId
     * @return array
     */
    private function getRuleWebsiteIds(int $ruleId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $select = $connection->select()
            ->from($table, ['website_id'])
            ->where('rule_id = ?', $ruleId)
            ->where('rule_type = ?', 'behavior_earning');

        return $connection->fetchCol($select);
    }

    /**
     * Get customer group IDs for rule
     *
     * @param int $ruleId
     * @return array
     */
    private function getRuleCustomerGroupIds(int $ruleId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');
        $select = $connection->select()
            ->from($table, ['customer_group_id'])
            ->where('rule_id = ?', $ruleId)
            ->where('rule_type = ?', 'behavior_earning');

        return $connection->fetchCol($select);
    }
}
