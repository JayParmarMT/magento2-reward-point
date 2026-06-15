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
 * GraphQL resolver for removing reward points from cart
 */
class RemoveRewardPoints implements ResolverInterface
{
    /**
     * @param CartManagementInterface $cartManagement
     */
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
    ) {
    }

    /**
     * Resolve remove reward points mutation
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return bool
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null,
    ): bool {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer is not authorized.'));
        }

        $cartId = (string) ($args['cartId'] ?? '');

        if (empty($cartId)) {
            throw new GraphQlInputException(__('cartId is required.'));
        }

        try {
            return $this->cartManagement->removePoints($cartId);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
    }
}
