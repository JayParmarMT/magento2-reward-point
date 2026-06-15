<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Customer group filter options for grid filterSelect — value is group name for LIKE matching
 */
class CustomerGroupFilter implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * Return customer group names as both value and label so grid LIKE filter matches column data
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();

        foreach ($collection as $group) {
            $name = $group->getCustomerGroupCode();
            $options[] = [
                'value' => $name,
                'label' => $name,
            ];
        }

        return $options;
    }
}
