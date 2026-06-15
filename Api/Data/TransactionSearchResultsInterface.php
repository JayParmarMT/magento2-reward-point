<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Transaction Search Results Interface
 *
 * @api
 */
interface TransactionSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return TransactionInterface[]
     */
    public function getItems(): array;

    /**
     * @param TransactionInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
