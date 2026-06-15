<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;

/**
 * GraphQL resolver for customer reward points account
 */
class Account implements ResolverInterface
{
    /**
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     */
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
    ) {
    }

    /**
     * Resolve reward points account for current customer
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<string, mixed>
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null,
    ): array {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer is not authorized.'));
        }

        $customerId = (int) $context->getUserId();
        $websiteId = (int) $context->getExtensionAttributes()->getStore()->getWebsiteId();

        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('Reward points account not found.'));
        }

        $currentTierData = null;
        $tierId = $account->getCurrentTierId();

        if ($tierId !== null) {
            try {
                $tier = $this->tierRepository->getById($tierId);
                $currentTierData = [
                    'tier_id' => $tier->getTierId(),
                    'name' => $tier->getName(),
                    'min_points' => $tier->getMinPoints(),
                    'earning_bonus_percent' => $tier->getEarningBonusPercent(),
                    'spending_discount_percent' => $tier->getSpendingDiscountPercent(),
                    'free_shipping' => $tier->isFreeShipping(),
                ];
            } catch (NoSuchEntityException $e) {
                // Tier may have been deleted; return null
            }
        }

        return [
            'balance' => $account->getPointsBalance(),
            'total_earned' => $account->getTotalEarned(),
            'total_spent' => $account->getTotalSpent(),
            'current_tier' => $currentTierData,
        ];
    }
}
