<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Meetanshi\RewardPoints\Api\CartManagementInterface;

/**
 * GraphQL resolver for applying reward points to cart
 */
class ApplyRewardPoints implements ResolverInterface
{
    /**
     * @param CartManagementInterface $cartManagement
     */
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
    ) {
    }

    /**
     * Resolve apply reward points mutation
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<string, mixed>
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
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

        $input = $args['input'] ?? [];
        $cartId = (string) ($input['cart_id'] ?? '');
        $points = (int) ($input['points'] ?? 0);

        if (empty($cartId)) {
            throw new GraphQlInputException(__('cart_id is required.'));
        }

        if ($points <= 0) {
            throw new GraphQlInputException(__('points must be a positive integer.'));
        }

        try {
            $success = $this->cartManagement->applyPoints($cartId, $points);

            return [
                'success' => $success,
                'discount_amount' => null,
                'points_applied' => $points,
                'message' => (string) __('Reward points applied successfully.'),
            ];
        } catch (LocalizedException $e) {
            return [
                'success' => false,
                'discount_amount' => null,
                'points_applied' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }
}
