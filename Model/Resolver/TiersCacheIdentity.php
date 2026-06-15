<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

/**
 * Cache identity for reward tiers GraphQL query
 */
class TiersCacheIdentity implements IdentityInterface
{
    private const CACHE_TAG = 'meetanshi_rp_tiers';

    /**
     * Get cache tags for the tiers resolver result
     *
     * @param array $resolvedData
     * @return string[]
     */
    public function getIdentities(array $resolvedData): array
    {
        return [self::CACHE_TAG];
    }
}
