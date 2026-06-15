<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Condition;

use Magento\Framework\Event\ManagerInterface;
use Magento\Rule\Model\Condition\Context;
use Magento\SalesRule\Model\Rule\Condition\Address;
use Magento\SalesRule\Model\Rule\Condition\Combine;
use Magento\SalesRule\Model\Rule\Condition\Product\Found;
use Magento\SalesRule\Model\Rule\Condition\Product\Subselect;
use Meetanshi\RewardPoints\Model\Rule\Condition\Customer;
use Meetanshi\RewardPoints\Model\Rule\Condition\CustomerFactory;

/**
 * Combine condition for cart earning rules — includes customer attribute conditions
 */
class CartCombine extends Combine
{
    /**
     * @param Context $context
     * @param ManagerInterface $eventManager
     * @param Address $conditionAddress
     * @param CustomerFactory $customerConditionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        private readonly Address $conditionAddress,
        private readonly CustomerFactory $customerConditionFactory,
        array $data = [],
    ) {
        parent::__construct($context, $eventManager, $conditionAddress, $data);
        $this->setType(CartCombine::class)
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
        $addressAttributes = $this->conditionAddress->loadAttributeOptions()->getAttributeOption();
        $addressOptions = [];

        foreach ($addressAttributes as $code => $label) {
            $addressOptions[] = [
                'value' => Address::class . '|' . $code,
                'label' => $label,
            ];
        }

        $customerCondition = $this->customerConditionFactory->create();
        $customerAttributes = $customerCondition->loadAttributeOptions()->getAttributeOption();
        $customerOptions = [];

        foreach ($customerAttributes as $code => $label) {
            $customerOptions[] = [
                'value' => Customer::class . '|' . $code,
                'label' => $label,
            ];
        }

        $conditions = [
            [
                'value' => '',
                'label' => __('Please choose a condition to add.'),
            ],
            [
                'value' => CartCombine::class,
                'label' => __('Conditions Combination'),
            ],
            [
                'label' => __('Cart Attribute'),
                'value' => $addressOptions,
            ],
            [
                'value' => Found::class,
                'label' => __('Product attribute combination'),
            ],
            [
                'value' => Subselect::class,
                'label' => __('Products subselection'),
            ],
            [
                'label' => __('Customer Attribute'),
                'value' => $customerOptions,
            ],
        ];

        return $conditions;
    }
}
