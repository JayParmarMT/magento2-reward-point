<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;

/**
 * GraphQL resolver for active reward tiers list
 */
class Tiers implements ResolverInterface
{
    /**
     * @param TierRepositoryInterface $tierRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly TierRepositoryInterface $tierRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    /**
     * Resolve active reward tiers
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<int, array<string, mixed>>
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null,
    ): array {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_active', 1)
            ->create();

        $tiers = $this->tierRepository->getList($searchCriteria);
        $result = [];

        foreach ($tiers->getItems() as $tier) {
            $result[] = [
                'tier_id' => $tier->getTierId(),
                'name' => $tier->getName(),
                'min_points' => $tier->getMinPoints(),
                'earning_bonus_percent' => $tier->getEarningBonusPercent(),
                'spending_discount_percent' => $tier->getSpendingDiscountPercent(),
                'free_shipping' => $tier->isFreeShipping(),
            ];
        }

        return $result;
    }
}
