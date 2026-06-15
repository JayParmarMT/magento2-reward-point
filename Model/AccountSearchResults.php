<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchResults;
use Meetanshi\RewardPoints\Api\Data\AccountSearchResultsInterface;

/**
 * Account search results model
 */
class AccountSearchResults extends SearchResults implements AccountSearchResultsInterface
{
    /**
     * Get accounts list
     *
     * @return \Meetanshi\RewardPoints\Api\Data\AccountInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems() ?? [];
    }

    /**
     * Set accounts list
     *
     * @param \Meetanshi\RewardPoints\Api\Data\AccountInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static
    {
        return parent::setItems($items);
    }
}
