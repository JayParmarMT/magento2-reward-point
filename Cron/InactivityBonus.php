<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\BalanceManagement;
use Meetanshi\RewardPoints\Model\Rule\Validator\BehaviorRuleConditionValidator;
use Meetanshi\RewardPoints\Model\Source\BehaviorEvent;
use Psr\Log\LoggerInterface;

/**
 * Inactivity bonus cron — awards points to customers inactive for a configured number of days
 */
class InactivityBonus
{
    private const DEFAULT_INACTIVITY_DAYS = 90;

    /**
     * @param ResourceConnection $resourceConnection
     * @param BalanceManagement $balanceManagement
     * @param TimezoneInterface $timezone
     * @param BehaviorRuleConditionValidator $behaviorRuleConditionValidator
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly BalanceManagement $balanceManagement,
        private readonly TimezoneInterface $timezone,
        private readonly BehaviorRuleConditionValidator $behaviorRuleConditionValidator,
        private readonly Config $config,
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
        if (!$this->config->isEnabled()) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $ruleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_rule');
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $loginLogTable = $this->resourceConnection->getTableName('customer_entity');
        $todayDate = $this->timezone->date()->format('Y-m-d');

        $ruleSelect = $connection->select()
            ->from($ruleTable)
            ->where('is_active = ?', 1)
            ->where('event_code = ?', BehaviorEvent::EVENT_INACTIVITY)
            ->where('from_date IS NULL OR from_date <= ?', $todayDate)
            ->where('to_date IS NULL OR to_date >= ?', $todayDate);

        $rules = $connection->fetchAll($ruleSelect);

        if (empty($rules)) {
            return;
        }

        $awarded = 0;

        foreach ($rules as $rule) {
            $ruleId = (int) $rule['rule_id'];
            $points = (int) $rule['points'];
            $inactivityDays = isset($rule['extra_data']) && $rule['extra_data']
                ? (int) (json_decode($rule['extra_data'], true)['inactivity_days'] ?? self::DEFAULT_INACTIVITY_DAYS)
                : self::DEFAULT_INACTIVITY_DAYS;

            $cutoffDate = $this->timezone->date(
                new \DateTime('-' . $inactivityDays . ' days'),
            )->format('Y-m-d H:i:s');

            $customerSelect = $connection->select()
                ->from(['ce' => $loginLogTable], ['entity_id', 'website_id', 'group_id'])
                ->where('ce.is_active = ?', 1)
                ->where(
                    'ce.entity_id NOT IN (?)',
                    $connection->select()
                        ->from($orderTable, ['customer_id'])
                        ->where('customer_id IS NOT NULL')
                        ->where('created_at >= ?', $cutoffDate),
                );

            $customerRows = $connection->fetchAll($customerSelect);

            foreach ($customerRows as $customerRow) {
                $customerId = (int) $customerRow['entity_id'];
                $websiteId = (int) ($customerRow['website_id'] ?? 1);
                $customerGroupId = (int) ($customerRow['group_id'] ?? 0);

                $existingLog = $connection->fetchRow(
                    $connection->select()
                        ->from($logTable, ['log_id', 'last_earned_date'])
                        ->where('customer_id = ?', $customerId)
                        ->where('rule_id = ?', $ruleId),
                );

                if ($existingLog) {
                    $lastEarned = $existingLog['last_earned_date'];
                    $daysSinceLast = $lastEarned
                        ? (int) $this->timezone->date()->diff(new \DateTime($lastEarned))->days
                        : PHP_INT_MAX;

                    if ($daysSinceLast < $inactivityDays) {
                        continue;
                    }
                }

                // Filter by website and customer group via junction tables
                if (!$this->behaviorRuleMatchesScope($ruleId, $websiteId, $customerGroupId)) {
                    continue;
                }

                // Validate rule conditions against the customer
                if (!$this->behaviorRuleConditionValidator->ruleMatchesCustomer(
                    $ruleId,
                    $rule['conditions_serialized'] ?? null,
                    $customerId,
                )) {
                    continue;
                }

                try {
                    $this->balanceManagement->addPoints(
                        $customerId,
                        $websiteId,
                        $points,
                        BehaviorEvent::EVENT_INACTIVITY,
                        (string) __('Inactivity bonus'),
                    );

                    $this->updateBehaviorLog($customerId, $ruleId, BehaviorEvent::EVENT_INACTIVITY, $points, $todayDate, $existingLog);

                    $awarded++;
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf(
                            '[RewardPoints] InactivityBonus error for customer %d: %s',
                            $customerId,
                            $e->getMessage(),
                        ),
                    );
                }
            }
        }

        if ($awarded > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] InactivityBonus: awarded points to %d customers.', $awarded),
            );
        }
    }

    /**
     * Check whether a behavior rule applies to the given website and customer group.
     *
     * Empty junction rows mean "applies to all".
     *
     * @param int $ruleId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return bool
     */
    private function behaviorRuleMatchesScope(int $ruleId, int $websiteId, int $customerGroupId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $websiteRows = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'behavior_earning'),
        );

        if (!empty($websiteRows) && !in_array($websiteId, array_map('intval', $websiteRows), true)) {
            return false;
        }

        $groupRows = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['customer_group_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'behavior_earning'),
        );

        if (!empty($groupRows) && !in_array($customerGroupId, array_map('intval', $groupRows), true)) {
            return false;
        }

        return true;
    }

    /**
     * Update or insert behavior log entry
     *
     * @param int $customerId
     * @param int $ruleId
     * @param string $eventCode
     * @param int $points
     * @param string $todayDate
     * @param array|false $existingLog
     * @return void
     */
    private function updateBehaviorLog(
        int $customerId,
        int $ruleId,
        string $eventCode,
        int $points,
        string $todayDate,
        array|false $existingLog,
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');

        if ($existingLog) {
            $connection->update(
                $logTable,
                [
                    'points_earned_today' => $points,
                    'points_earned_this_month' => (int) $existingLog['points_earned_this_month'] + $points,
                    'points_earned_this_year' => (int) $existingLog['points_earned_this_year'] + $points,
                    'points_earned_lifetime' => (int) $existingLog['points_earned_lifetime'] + $points,
                    'last_earned_date' => $todayDate,
                ],
                ['log_id = ?' => (int) $existingLog['log_id']],
            );
        } else {
            $connection->insert($logTable, [
                'customer_id' => $customerId,
                'rule_id' => $ruleId,
                'event_code' => $eventCode,
                'points_earned_today' => $points,
                'points_earned_this_month' => $points,
                'points_earned_this_year' => $points,
                'points_earned_lifetime' => $points,
                'last_earned_date' => $todayDate,
            ]);
        }
    }
}
