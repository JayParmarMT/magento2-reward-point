<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\TierCalculator;
use Psr\Log\LoggerInterface;

/**
 * Tier recalculate cron — updates customer tier based on earned points or invoice amount
 */
class TierRecalculate
{
    private const BATCH_SIZE = 500;
    private const CONFIG_BASIS = 'meetanshi_rewardpoints/tier/basis';
    private const CONFIG_WINDOW_DAYS = 'meetanshi_rewardpoints/tier/period';

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param TimezoneInterface $timezone
     * @param TierCalculator $tierCalculator
     * @param Config $config
     * @param EventManager $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface $timezone,
        private readonly TierCalculator $tierCalculator,
        private readonly Config $config,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isTierEnabled()) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $accountTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_account');
        $tierTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_tier');
        $tierHistoryTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_tier_history');

        $basis = $this->scopeConfig->getValue(
            self::CONFIG_BASIS,
            ScopeInterface::SCOPE_STORE,
        ) ?? 'points';

        $windowDays = (int) ($this->scopeConfig->getValue(
            self::CONFIG_WINDOW_DAYS,
            ScopeInterface::SCOPE_STORE,
        ) ?? 0);

        $tiersSelect = $connection->select()
            ->from($tierTable, ['tier_id', 'min_points', 'sort_order'])
            ->where('is_active = ?', 1)
            ->order('min_points DESC');

        $tiers = $connection->fetchAll($tiersSelect);

        if (empty($tiers)) {
            return;
        }

        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $websiteJunctionTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgJunctionTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $offset = 0;
        $totalUpdated = 0;

        do {
            $accountSelect = $connection->select()
                ->from(
                    ['a' => $accountTable],
                    ['account_id', 'customer_id', 'website_id', 'total_earned', 'lifetime_invoice_amount', 'current_tier_id'],
                )
                ->joinLeft(
                    ['ce' => $customerTable],
                    'a.customer_id = ce.entity_id',
                    ['customer_group_id' => 'ce.group_id'],
                )
                ->limit(self::BATCH_SIZE, $offset);

            $accounts = $connection->fetchAll($accountSelect);

            foreach ($accounts as $account) {
                $customerId = (int) $account['customer_id'];
                $accountId = (int) $account['account_id'];
                $websiteId = (int) $account['website_id'];
                $customerGroupId = (int) ($account['customer_group_id'] ?? 0);
                $currentTierId = $account['current_tier_id'] ? (int) $account['current_tier_id'] : null;

                $metricValue = $basis === 'invoice_amount'
                    ? (float) $account['lifetime_invoice_amount']
                    : (int) $account['total_earned'];

                if ($windowDays > 0) {
                    $metricValue = $this->getRollingWindowMetric($customerId, $basis, $windowDays);
                }

                $newTierId = null;

                foreach ($tiers as $tier) {
                    $tierId = (int) $tier['tier_id'];

                    if ($metricValue < (int) $tier['min_points']) {
                        continue;
                    }

                    if (!$this->tierMatchesScope($tierId, $websiteId, $customerGroupId, $websiteJunctionTable, $cgJunctionTable, $connection)) {
                        continue;
                    }

                    $newTierId = $tierId;
                    break;
                }

                if ($newTierId === $currentTierId) {
                    continue;
                }

                // Respect Auto Demote setting — skip downgrade when disabled
                if ($currentTierId !== null && $newTierId !== null) {
                    $oldTierPoints = $this->getTierMinPoints($tiers, $currentTierId);
                    $newTierPoints = $this->getTierMinPoints($tiers, $newTierId);

                    if ($newTierPoints < $oldTierPoints && !$this->config->isAutoDemote()) {
                        continue;
                    }
                }

                $connection->update(
                    $accountTable,
                    ['current_tier_id' => $newTierId],
                    ['account_id = ?' => $accountId],
                );

                $changeType = 'initial';

                if ($currentTierId !== null && $newTierId !== null) {
                    $oldTierPoints = $this->getTierMinPoints($tiers, $currentTierId);
                    $newTierPoints = $this->getTierMinPoints($tiers, $newTierId);
                    $changeType = $newTierPoints > $oldTierPoints ? 'up' : 'down';
                } elseif ($newTierId !== null) {
                    $changeType = 'initial';
                }

                $connection->insert($tierHistoryTable, [
                    'customer_id' => $customerId,
                    'website_id' => $websiteId,
                    'from_tier_id' => $currentTierId,
                    'to_tier_id' => $newTierId,
                    'change_type' => $changeType,
                ]);

                // Dispatch tier changed event
                $this->eventManager->dispatch('meetanshi_rewardpoints_tier_changed', [
                    'customer_id' => $customerId,
                    'website_id' => $websiteId,
                    'old_tier_id' => $currentTierId,
                    'new_tier_id' => $newTierId,
                    'change_type' => $changeType,
                ]);

                $totalUpdated++;
            }

            $offset += self::BATCH_SIZE;
        } while (count($accounts) === self::BATCH_SIZE);

        if ($totalUpdated > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] TierRecalculate: updated %d customer tiers.', $totalUpdated),
            );
        }
    }

    /**
     * Check whether a tier applies to the given website and customer group.
     *
     * Empty junction rows mean "applies to all" — a tier with no website rows
     * applies to every website; a tier with no group rows applies to every group.
     *
     * @param int $tierId
     * @param int $websiteId
     * @param int $customerGroupId
     * @param string $websiteTable
     * @param string $cgTable
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return bool
     */
    private function tierMatchesScope(
        int $tierId,
        int $websiteId,
        int $customerGroupId,
        string $websiteTable,
        string $cgTable,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
    ): bool {
        $websiteRows = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('rule_id = ?', $tierId)
                ->where('rule_type = ?', 'tier'),
        );

        if (!empty($websiteRows) && !in_array($websiteId, array_map('intval', $websiteRows), true)) {
            return false;
        }

        $groupRows = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['customer_group_id'])
                ->where('rule_id = ?', $tierId)
                ->where('rule_type = ?', 'tier'),
        );

        if (!empty($groupRows) && !in_array($customerGroupId, array_map('intval', $groupRows), true)) {
            return false;
        }

        return true;
    }

    /**
     * Get metric value using rolling window
     *
     * @param int $customerId
     * @param string $basis
     * @param int $windowDays
     * @return float
     */
    private function getRollingWindowMetric(int $customerId, string $basis, int $windowDays): float
    {
        $connection = $this->resourceConnection->getConnection();

        if ($basis === 'invoice_amount') {
            $invoiceTable = $this->resourceConnection->getTableName('sales_invoice');
            $orderTable = $this->resourceConnection->getTableName('sales_order');
            $cutoff = $this->timezone->date(new \DateTime('-' . $windowDays . ' days'))->format('Y-m-d H:i:s');

            $select = $connection->select()
                ->from(['si' => $invoiceTable], ['total' => 'SUM(si.grand_total)'])
                ->join(['so' => $orderTable], 'si.order_id = so.entity_id', [])
                ->where('so.customer_id = ?', $customerId)
                ->where('si.created_at >= ?', $cutoff);

            return (float) ($connection->fetchOne($select) ?? 0);
        }

        $txnTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_transaction');
        $accountTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_account');
        $cutoff = $this->timezone->date(new \DateTime('-' . $windowDays . ' days'))->format('Y-m-d H:i:s');

        $select = $connection->select()
            ->from(['t' => $txnTable], ['total' => 'SUM(t.points_delta)'])
            ->join(['a' => $accountTable], 't.account_id = a.account_id', [])
            ->where('a.customer_id = ?', $customerId)
            ->where('t.points_delta > 0')
            ->where('t.created_at >= ?', $cutoff);

        return (float) ($connection->fetchOne($select) ?? 0);
    }

    /**
     * Get minimum points threshold for a tier ID from the tiers array
     *
     * @param array $tiers
     * @param int $tierId
     * @return int
     */
    private function getTierMinPoints(array $tiers, int $tierId): int
    {
        foreach ($tiers as $tier) {
            if ((int) $tier['tier_id'] === $tierId) {
                return (int) $tier['min_points'];
            }
        }

        return 0;
    }
}
