<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Website options source for multiselect fields
 */
class Website implements OptionSourceInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Return array of websites as options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->storeManager->getWebsites() as $website) {
            $options[] = [
                'value' => (string) $website->getId(),
                'label' => $website->getName(),
            ];
        }

        return $options;
    }
}
