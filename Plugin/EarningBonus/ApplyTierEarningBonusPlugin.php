<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\EarningBonus;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\TierHistory as TierHistoryResource;
use Meetanshi\RewardPoints\Model\TierCalculator;
use Meetanshi\RewardPoints\Model\TierHistoryFactory;
use Psr\Log\LoggerInterface;

/**
 * Plugin to apply tier earning bonus after points are added, and to re-evaluate
 * tier eligibility (upgrade AND downgrade) after any balance change.
 */
class ApplyTierEarningBonusPlugin
{
    /**
     * Action codes that should NOT trigger a tier bonus award.
     * Refund and tier adjustment codes must be excluded to avoid infinite loops
     * and incorrect bonus grants on deductions.
     */
    private const EXCLUDED_BONUS_ACTION_CODES = [
        'admin_credit',
        'admin_debit',
        'refund',
        'refund_earn',
        'refund_spend',
        'refund_tier_bonus',
        'tier_up',
        'tier_down',
        'referral_referrer',
        'referral_referee',
    ];

    /**
     * @param TierCalculator $tierCalculator
     * @param BalanceManagementInterface $balanceManagement
     * @param AccountRepositoryInterface $accountRepository
     * @param TierHistoryFactory $tierHistoryFactory
     * @param TierHistoryResource $tierHistoryResource
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param EventManager $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TierCalculator $tierCalculator,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierHistoryFactory $tierHistoryFactory,
        private readonly TierHistoryResource $tierHistoryResource,
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * After addPoints: award tier bonus if applicable, then sync tier (upgrade).
     *
     * @param BalanceManagementInterface $subject
     * @param TransactionInterface $result
     * @param int $customerId
     * @param int $websiteId
     * @param int $points
     * @param string $actionCode
     * @param string|null $comment
     * @param int|null $expireAfterDays
     * @param bool $notifyCustomer
     * @param array $extraData
     * @return TransactionInterface
     */
    public function afterAddPoints(
        BalanceManagementInterface $subject,
        TransactionInterface $result,
        int $customerId,
        int $websiteId,
        int $points,
        string $actionCode,
        ?string $comment = null,
        ?int $expireAfterDays = null,
        bool $notifyCustomer = false,
        array $extraData = [],
    ): TransactionInterface {
        if (!$this->config->isTierEnabled()) {
            return $result;
        }

        // Award tier bonus only for qualifying earn actions
        if (!in_array($actionCode, self::EXCLUDED_BONUS_ACTION_CODES, true)) {
            try {
                $customerGroupId = $this->getCustomerGroupId($customerId);
                $tier = $this->tierCalculator->getEligibleTier($customerId, $websiteId, $customerGroupId);
                $this->syncAccountTier($customerId, $websiteId, $tier ? (int) $tier->getTierId() : null);

                if ($tier && ($bonusPercent = $tier->getEarningBonusPercent()) > 0) {
                    $bonusPoints = (int) floor($points * ($bonusPercent / 100));

                    if ($bonusPoints > 0) {
                        $this->balanceManagement->addPoints(
                            $customerId,
                            $websiteId,
                            $bonusPoints,
                            TransactionInterface::ACTION_TIER_UP,
                            (string) __('Tier bonus (%1% on %2 base points)', $bonusPercent, $points),
                            $expireAfterDays,
                            false,
                            $extraData,
                        );
                    }
                }
            } catch (LocalizedException $e) {
                $this->logger->warning(
                    'RewardPoints: ApplyTierEarningBonusPlugin failed to award tier bonus',
                    [
                        'customer_id' => $customerId,
                        'action_code' => $actionCode,
                        'message' => $e->getMessage(),
                    ],
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    'RewardPoints: ApplyTierEarningBonusPlugin unexpected error on addPoints',
                    ['exception' => $e],
                );
            }
        } else {
            // Even for excluded codes, still re-evaluate the tier in case an add
            // (e.g. refund_spend restoring points) moves the customer back up.
            $this->reevaluateTier($customerId, $websiteId);
        }

        return $result;
    }

    /**
     * After subtractPoints: re-evaluate tier eligibility for potential downgrade.
     *
     * @param BalanceManagementInterface $subject
     * @param TransactionInterface $result
     * @param int $customerId
     * @param int $websiteId
     * @param int $points
     * @param string $actionCode
     * @param string|null $comment
     * @param array $extraData
     * @return TransactionInterface
     */
    public function afterSubtractPoints(
        BalanceManagementInterface $subject,
        TransactionInterface $result,
        int $customerId,
        int $websiteId,
        int $points,
        string $actionCode,
        ?string $comment = null,
        array $extraData = [],
    ): TransactionInterface {
        if ($this->config->isTierEnabled()) {
            $this->reevaluateTier($customerId, $websiteId);
        }

        return $result;
    }

    /**
     * Re-evaluate the customer's eligible tier and sync if it has changed.
     *
     * Called after both additions and subtractions so upgrades and downgrades
     * are reflected immediately rather than waiting for the nightly cron.
     *
     * @param int $customerId
     * @param int $websiteId
     * @return void
     */
    private function reevaluateTier(int $customerId, int $websiteId): void
    {
        try {
            $customerGroupId = $this->getCustomerGroupId($customerId);
            $tier = $this->tierCalculator->getEligibleTier($customerId, $websiteId, $customerGroupId);
            $this->syncAccountTier($customerId, $websiteId, $tier ? (int) $tier->getTierId() : null);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: ApplyTierEarningBonusPlugin failed to re-evaluate tier',
                ['customer_id' => $customerId, 'message' => $e->getMessage()],
            );
        }
    }

    /**
     * Get the customer group ID for a customer from the database.
     *
     * Uses a direct DB lookup to avoid full customer model hydration overhead.
     *
     * @param int $customerId
     * @return int
     */
    private function getCustomerGroupId(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $groupId = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('customer_entity'), ['group_id'])
                ->where('entity_id = ?', $customerId),
        );

        return $groupId !== false ? (int) $groupId : 0;
    }

    /**
     * Sync account current_tier_id when it has changed.
     *
     * On a real change this method:
     *   - updates the account record
     *   - writes a tier_history row
     *   - dispatches meetanshi_rewardpoints_tier_changed so email / observers fire
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int|null $newTierId
     * @return void
     */
    private function syncAccountTier(int $customerId, int $websiteId, ?int $newTierId): void
    {
        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $oldTierId = $account->getCurrentTierId();

            // Nothing to do if tier hasn't changed
            if ($oldTierId === $newTierId) {
                return;
            }

            // Persist updated tier on the account
            $account->setCurrentTierId($newTierId);
            $this->accountRepository->save($account);

            // Determine direction
            $changeType = $this->resolveChangeType($oldTierId, $newTierId);

            // Write history record
            $history = $this->tierHistoryFactory->create();
            $history->setData([
                'account_id'  => $account->getAccountId(),
                'customer_id' => $customerId,
                'website_id'  => $websiteId,
                'from_tier_id' => $oldTierId,
                'to_tier_id'  => $newTierId,
                'change_type' => $changeType,
            ]);
            $this->tierHistoryResource->save($history);

            // Dispatch event for email notifications and any other observers
            $this->eventManager->dispatch('meetanshi_rewardpoints_tier_changed', [
                'customer_id' => $customerId,
                'website_id'  => $websiteId,
                'old_tier_id' => $oldTierId,
                'new_tier_id' => $newTierId,
                'change_type' => $changeType,
            ]);

            $this->logger->info(
                sprintf(
                    'RewardPoints: tier %s for customer %d (website %d): tier %s → %s',
                    $changeType,
                    $customerId,
                    $websiteId,
                    $oldTierId ?? 'none',
                    $newTierId ?? 'none',
                ),
            );
        } catch (NoSuchEntityException) {
            // No account yet — nothing to sync
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: ApplyTierEarningBonusPlugin failed to sync account tier',
                ['customer_id' => $customerId, 'message' => $e->getMessage()],
            );
        }
    }

    /**
     * Determine whether the tier change is an upgrade, downgrade, or initial assignment.
     *
     * Uses the tier min_points stored on the TierInterface for comparison.
     *
     * @param int|null $oldTierId
     * @param int|null $newTierId
     * @return string  'up' | 'down' | 'initial' | 'removed'
     */
    private function resolveChangeType(?int $oldTierId, ?int $newTierId): string
    {
        if ($oldTierId === null && $newTierId !== null) {
            return 'initial';
        }

        if ($newTierId === null) {
            return 'removed';
        }

        try {
            $oldMinPoints = $this->tierCalculator->getTierMinPointsById($oldTierId);
            $newMinPoints = $this->tierCalculator->getTierMinPointsById($newTierId);

            return $newMinPoints > $oldMinPoints ? 'up' : 'down';
        } catch (\Exception) {
            return 'up';
        }
    }
}
