<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\BalanceManagement;
use Meetanshi\RewardPoints\Model\Rule\Validator\BehaviorRuleConditionValidator;
use Meetanshi\RewardPoints\Model\Source\BehaviorEvent;
use Psr\Log\LoggerInterface;

/**
 * Birthday bonus cron — awards points to customers whose birthday is today
 */
class BirthdayBonus
{
    /**
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param BalanceManagement $balanceManagement
     * @param TimezoneInterface $timezone
     * @param BehaviorRuleConditionValidator $behaviorRuleConditionValidator
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerCollectionFactory $customerCollectionFactory,
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

        $today = $this->timezone->date()->format('m-d');
        $connection = $this->resourceConnection->getConnection();
        $ruleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_rule');
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');
        $todayDate = $this->timezone->date()->format('Y-m-d');

        $ruleSelect = $connection->select()
            ->from($ruleTable)
            ->where('is_active = ?', 1)
            ->where('event_code = ?', BehaviorEvent::EVENT_BIRTHDAY)
            ->where('from_date IS NULL OR from_date <= ?', $todayDate)
            ->where('to_date IS NULL OR to_date >= ?', $todayDate);

        $rules = $connection->fetchAll($ruleSelect);

        if (empty($rules)) {
            return;
        }

        $customerCollection = $this->customerCollectionFactory->create();
        $customerCollection->addAttributeToSelect(['dob', 'firstname', 'lastname', 'email', 'group_id', 'website_id']);
        $customerCollection->addAttributeToFilter(
            'dob',
            ['like' => '%' . $today . '%'],
        );

        $awarded = 0;

        foreach ($customerCollection as $customer) {
            $customerId = (int) $customer->getId();

            foreach ($rules as $rule) {
                $ruleId = (int) $rule['rule_id'];

                $existingLogSelect = $connection->select()
                    ->from($logTable, ['log_id', 'last_earned_date', 'points_earned_lifetime'])
                    ->where('customer_id = ?', $customerId)
                    ->where('rule_id = ?', $ruleId);

                $existingLog = $connection->fetchRow($existingLogSelect);

                if ($existingLog && $existingLog['last_earned_date'] === $todayDate) {
                    continue;
                }

                $websiteId = (int) ($customer->getData('website_id') ?? 1);
                $customerGroupId = (int) ($customer->getData('group_id') ?? 0);

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

                $capLifetime = (int) ($rule['cap_lifetime'] ?? 0);

                if ($capLifetime > 0) {
                    $lifetimePoints = $existingLog ? (int) ($existingLog['points_earned_lifetime'] ?? 0) : 0;

                    if ($lifetimePoints >= $capLifetime) {
                        continue;
                    }
                }

                try {
                    $points = (int) $rule['points'];

                    $this->balanceManagement->addPoints(
                        $customerId,
                        $websiteId,
                        $points,
                        BehaviorEvent::EVENT_BIRTHDAY,
                        (string) __('Birthday bonus'),
                    );

                    $this->updateBehaviorLog($customerId, $ruleId, BehaviorEvent::EVENT_BIRTHDAY, $points, $todayDate);

                    $awarded++;
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf(
                            '[RewardPoints] BirthdayBonus error for customer %d: %s',
                            $customerId,
                            $e->getMessage(),
                        ),
                    );
                }
            }
        }

        if ($awarded > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] BirthdayBonus: awarded points to %d customers.', $awarded),
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
     * @return void
     */
    private function updateBehaviorLog(
        int $customerId,
        int $ruleId,
        string $eventCode,
        int $points,
        string $todayDate,
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');

        $existing = $connection->fetchRow(
            $connection->select()
                ->from($logTable)
                ->where('customer_id = ?', $customerId)
                ->where('rule_id = ?', $ruleId),
        );

        if ($existing) {
            $connection->update(
                $logTable,
                [
                    'points_earned_today' => $points,
                    'points_earned_this_month' => (int) $existing['points_earned_this_month'] + $points,
                    'points_earned_this_year' => (int) $existing['points_earned_this_year'] + $points,
                    'points_earned_lifetime' => (int) $existing['points_earned_lifetime'] + $points,
                    'last_earned_date' => $todayDate,
                ],
                ['log_id = ?' => (int) $existing['log_id']],
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
