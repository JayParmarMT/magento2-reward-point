<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchResults;
use Meetanshi\RewardPoints\Api\Data\TierSearchResultsInterface;

/**
 * Tier search results model
 */
class TierSearchResults extends SearchResults implements TierSearchResultsInterface
{
    /**
     * Get tiers list
     *
     * @return \Meetanshi\RewardPoints\Api\Data\TierInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems() ?? [];
    }

    /**
     * Set tiers list
     *
     * @param \Meetanshi\RewardPoints\Api\Data\TierInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static
    {
        return parent::setItems($items);
    }
}
