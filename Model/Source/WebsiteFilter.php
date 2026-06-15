<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Website filter options for grid filterSelect — value is website name for LIKE matching
 */
class WebsiteFilter implements OptionSourceInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Return website names as both value and label so grid LIKE filter matches column data
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->storeManager->getWebsites() as $website) {
            $name = $website->getName();
            $options[] = [
                'value' => $name,
                'label' => $name,
            ];
        }

        return $options;
    }
}
