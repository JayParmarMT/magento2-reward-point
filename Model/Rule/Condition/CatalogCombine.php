<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Condition;

use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Context;
use Meetanshi\RewardPoints\Model\Rule\Condition\CatalogProductFactory;

/**
 * Combine condition for catalog earning rules
 */
class CatalogCombine extends Combine
{
    /**
     * @param Context $context
     * @param CatalogProductFactory $catalogProductFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CatalogProductFactory $catalogProductFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
        $this->setType(CatalogCombine::class)
            ->setAggregator('all')
            ->setValue(1);
    }

    /**
     * Get new child select options
     *
     * @return array
     */
    public function getNewChildSelectOptions(): array
    {
        $productAttributes = $this->catalogProductFactory->create()->loadAttributeOptions()->getAttributeOption();
        $attributeOptions = [];

        foreach ($productAttributes as $code => $label) {
            $attributeOptions[] = [
                'value' => CatalogProduct::class . '|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive(
            $conditions,
            [
                [
                    'value' => CatalogCombine::class,
                    'label' => __('Conditions Combination'),
                ],
                [
                    'label' => __('Product Attribute'),
                    'value' => $attributeOptions,
                ],
            ],
        );

        return $conditions;
    }
}
