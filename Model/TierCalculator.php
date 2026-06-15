<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\CollectionFactory as TierCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Tier Calculator — determines eligible tier and applies tier benefits
 */
class TierCalculator
{
    private const XML_PATH_TIER_BASIS = 'meetanshi_rewardpoints/tier/basis';
    private const XML_PATH_TIER_PERIOD_DAYS = 'meetanshi_rewardpoints/tier/period_days';
    private const XML_PATH_TIER_ORDER_STATUSES = 'meetanshi_rewardpoints/tier/order_statuses';
    private const TIER_BASIS_EARNED_POINTS = 'earned_points';
    private const TIER_BASIS_SPENT_AMOUNT = 'spent_amount';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param TierCollectionFactory $tierCollectionFactory
     * @param AccountResource $accountResource
     * @param TierRepositoryInterface $tierRepository
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TierCollectionFactory $tierCollectionFactory,
        private readonly AccountResource $accountResource,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get the highest eligible tier for a customer.
     *
     * Returns null immediately when the tier programme is disabled.
     * Filters tiers by website and customer group using junction tables — a tier
     * with no junction rows applies to all websites / all customer groups.
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return TierInterface|null
     */
    public function getEligibleTier(int $customerId, int $websiteId, int $customerGroupId = 0): ?TierInterface
    {
        if (!$this->config->isTierEnabled()) {
            return null;
        }

        $basis = $this->getBasis($websiteId);
        $periodDays = $this->getPeriodDays($websiteId);

        $customerValue = $basis === self::TIER_BASIS_SPENT_AMOUNT
            ? $this->getSpentAmountInPeriod($customerId, $periodDays)
            : $this->getEarnedPointsInPeriod($customerId, $websiteId, $periodDays);

        // Get all active tiers sorted by min_points descending
        $collection = $this->tierCollectionFactory->create();
        $collection->addFieldToFilter(TierInterface::IS_ACTIVE, 1);
        $collection->setOrder(TierInterface::MIN_POINTS, 'DESC');

        foreach ($collection->getItems() as $tier) {
            if ($customerValue < $tier->getMinPoints()) {
                continue;
            }

            if (!$this->tierMatchesScope((int) $tier->getTierId(), $websiteId, $customerGroupId)) {
                continue;
            }

            return $tier;
        }

        return null;
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
     * @return bool
     */
    private function tierMatchesScope(int $tierId, int $websiteId, int $customerGroupId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

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
     * Apply tier earning bonus to base points.
     *
     * Returns the original points unchanged when the tier programme is disabled.
     *
     * @param int $points
     * @param int $customerId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return int
     */
    public function applyTierBenefits(int $points, int $customerId, int $websiteId, int $customerGroupId = 0): int
    {
        if (!$this->config->isTierEnabled()) {
            return $points;
        }

        $tier = $this->getEligibleTier($customerId, $websiteId, $customerGroupId);

        if (!$tier) {
            return $points;
        }

        $bonusPercent = $tier->getEarningBonusPercent();

        if ($bonusPercent <= 0) {
            return $points;
        }

        return (int) floor($points * (1 + $bonusPercent / 100));
    }

    /**
     * Get points needed after applying spending discount from tier.
     *
     * Returns the original points unchanged when the tier programme is disabled.
     *
     * @param int $points
     * @param int $customerId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return int
     */
    public function getSpendingDiscount(int $points, int $customerId, int $websiteId, int $customerGroupId = 0): int
    {
        if (!$this->config->isTierEnabled()) {
            return $points;
        }

        $tier = $this->getEligibleTier($customerId, $websiteId, $customerGroupId);

        if (!$tier) {
            return $points;
        }

        $discountPercent = $tier->getSpendingDiscountPercent();

        if ($discountPercent <= 0) {
            return $points;
        }

        return (int) ceil($points * (1 - $discountPercent / 100));
    }

    /**
     * Get total earned points in rolling window
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $periodDays
     * @return int
     */
    private function getEarnedPointsInPeriod(int $customerId, int $websiteId, int $periodDays): int
    {
        $connection = $this->accountResource->getConnection();
        $txnTable = $connection->getTableName('meetanshi_rewardpoints_transaction');
        $accountTable = $this->accountResource->getMainTable();

        $select = $connection->select()
            ->from(['a' => $accountTable], [])
            ->join(
                ['t' => $txnTable],
                'a.account_id = t.account_id',
                ['total' => new \Magento\Framework\DB\Expr('COALESCE(SUM(t.points_delta), 0)')],
            )
            ->where('a.customer_id = ?', $customerId)
            ->where('a.website_id = ?', $websiteId)
            ->where('t.points_delta > 0')
            ->where("t.status IN ('active', 'pending')");

        if ($periodDays > 0) {
            $since = date('Y-m-d H:i:s', strtotime("-{$periodDays} days"));
            $select->where('t.created_at >= ?', $since);
        }

        return (int) $connection->fetchOne($select);
    }

    /**
     * Get total invoice amount for customer in rolling window
     *
     * @param int $customerId
     * @param int $periodDays
     * @return float
     */
    private function getSpentAmountInPeriod(int $customerId, int $periodDays): float
    {
        $connection = $this->accountResource->getConnection();
        $orderStatuses = $this->getOrderStatuses();

        $select = $connection->select()
            ->from(
                $connection->getTableName('sales_order'),
                ['total' => new \Magento\Framework\DB\Expr('COALESCE(SUM(grand_total), 0)')],
            )
            ->where('customer_id = ?', $customerId)
            ->where('status IN (?)', $orderStatuses);

        if ($periodDays > 0) {
            $since = date('Y-m-d H:i:s', strtotime("-{$periodDays} days"));
            $select->where('created_at >= ?', $since);
        }

        return (float) $connection->fetchOne($select);
    }

    /**
     * Get the minimum points threshold for a tier by its ID.
     *
     * Used by ApplyTierEarningBonusPlugin to determine upgrade vs downgrade
     * direction when the customer's tier changes.
     *
     * @param int $tierId
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTierMinPointsById(int $tierId): int
    {
        $tier = $this->tierRepository->getById($tierId);

        return (int) $tier->getMinPoints();
    }

    /**
     * Get tier calculation basis from config
     *
     * @param int $websiteId
     * @return string
     */
    private function getBasis(int $websiteId): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_BASIS,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId,
        ) ?: self::TIER_BASIS_EARNED_POINTS;
    }

    /**
     * Get rolling window period days from config
     *
     * @param int $websiteId
     * @return int
     */
    private function getPeriodDays(int $websiteId): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_PERIOD_DAYS,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId,
        );
    }

    /**
     * Get order statuses for spent amount calculation
     *
     * @return string[]
     */
    private function getOrderStatuses(): array
    {
        $statuses = (string) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_ORDER_STATUSES,
            ScopeInterface::SCOPE_STORE,
        );

        if (empty($statuses)) {
            return ['complete'];
        }

        return array_map('trim', explode(',', $statuses));
    }
}
