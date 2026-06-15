<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Filters\Type;

use Magento\Ui\Component\Filters\Type\Select;

/**
 * Filter type that applies a LIKE condition instead of EQ.
 *
 * Used for GROUP_CONCAT columns (websites, customer_groups) where the stored
 * value is a comma-separated string and partial matching is required.
 */
class SelectLike extends Select
{
    /**
     * Apply LIKE filter instead of exact-match EQ
     *
     * @return void
     */
    protected function applyFilter(): void
    {
        if (!isset($this->filterData[$this->getName()])) {
            return;
        }

        $value = $this->filterData[$this->getName()];

        if (empty($value) && !is_numeric($value)) {
            return;
        }

        $filter = $this->filterBuilder
            ->setConditionType('like')
            ->setField($this->getName())
            ->setValue('%' . $value . '%')
            ->create();

        $this->getContext()->getDataProvider()->addFilter($filter);
    }
}
