<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Account Search Results Interface
 *
 * @api
 */
interface AccountSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return AccountInterface[]
     */
    public function getItems(): array;

    /**
     * @param AccountInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
