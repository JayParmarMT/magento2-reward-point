<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchResults;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterface;

/**
 * Transaction search results model
 */
class TransactionSearchResults extends SearchResults implements TransactionSearchResultsInterface
{
    /**
     * Get transactions list
     *
     * @return \Meetanshi\RewardPoints\Api\Data\TransactionInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems() ?? [];
    }

    /**
     * Set transactions list
     *
     * @param \Meetanshi\RewardPoints\Api\Data\TransactionInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static
    {
        return parent::setItems($items);
    }
}
