<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Rule Junction Resource — manages meetanshi_rewardpoints_rule_website
 * and meetanshi_rewardpoints_rule_customer_group rows.
 */
class RuleJunction
{
    private const TABLE_WEBSITE = 'meetanshi_rewardpoints_rule_website';
    private const TABLE_CG = 'meetanshi_rewardpoints_rule_customer_group';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    /**
     * Save website associations for a rule
     *
     * @param int $ruleId
     * @param string $ruleType
     * @param int[] $websiteIds
     * @return void
     */
    public function saveWebsites(int $ruleId, string $ruleType, array $websiteIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_WEBSITE);

        $connection->delete($table, [
            'rule_id = ?' => $ruleId,
            'rule_type = ?' => $ruleType,
        ]);

        if (empty($websiteIds)) {
            return;
        }

        $rows = [];

        foreach ($websiteIds as $websiteId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => $ruleType,
                'website_id' => (int) $websiteId,
            ];
        }

        $connection->insertMultiple($table, $rows);
    }

    /**
     * Save customer group associations for a rule
     *
     * @param int $ruleId
     * @param string $ruleType
     * @param int[] $customerGroupIds
     * @return void
     */
    public function saveCustomerGroups(int $ruleId, string $ruleType, array $customerGroupIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_CG);

        $connection->delete($table, [
            'rule_id = ?' => $ruleId,
            'rule_type = ?' => $ruleType,
        ]);

        if (empty($customerGroupIds)) {
            return;
        }

        $rows = [];

        foreach ($customerGroupIds as $groupId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => $ruleType,
                'customer_group_id' => (int) $groupId,
            ];
        }

        $connection->insertMultiple($table, $rows);
    }

    /**
     * Get website IDs for a rule
     *
     * @param int $ruleId
     * @param string $ruleType
     * @return int[]
     */
    public function getWebsiteIds(int $ruleId, string $ruleType): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_WEBSITE);

        $select = $connection->select()
            ->from($table, ['website_id'])
            ->where('rule_id = ?', $ruleId)
            ->where('rule_type = ?', $ruleType);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Get customer group IDs for a rule
     *
     * @param int $ruleId
     * @param string $ruleType
     * @return int[]
     */
    public function getCustomerGroupIds(int $ruleId, string $ruleType): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_CG);

        $select = $connection->select()
            ->from($table, ['customer_group_id'])
            ->where('rule_id = ?', $ruleId)
            ->where('rule_type = ?', $ruleType);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Delete all junction rows for a rule
     *
     * @param int $ruleId
     * @param string $ruleType
     * @return void
     */
    public function deleteByRule(int $ruleId, string $ruleType): void
    {
        $connection = $this->resourceConnection->getConnection();
        $whereCondition = [
            'rule_id = ?' => $ruleId,
            'rule_type = ?' => $ruleType,
        ];

        $connection->delete(
            $this->resourceConnection->getTableName(self::TABLE_WEBSITE),
            $whereCondition,
        );
        $connection->delete(
            $this->resourceConnection->getTableName(self::TABLE_CG),
            $whereCondition,
        );
    }
}
