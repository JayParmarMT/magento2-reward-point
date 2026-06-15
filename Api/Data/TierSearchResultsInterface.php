<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Tier search results interface
 *
 * @api
 */
interface TierSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get tiers
     *
     * @return \Meetanshi\RewardPoints\Api\Data\TierInterface[]
     */
    public function getItems(): array;

    /**
     * Set tiers
     *
     * @param \Meetanshi\RewardPoints\Api\Data\TierInterface[] $items
     * @return static
     */
    public function setItems(array $items): static;
}
