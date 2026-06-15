<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Magento\Rule\Model\Action\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule as SpendingRuleResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\Collection as SpendingRuleCollection;
use Meetanshi\RewardPoints\Model\Rule\Condition\CartCombine;
use Meetanshi\RewardPoints\Model\Rule\Condition\CartCombineFactory;

/**
 * Spending Rule Condition model — extends AbstractModel for conditions tree support
 */
class SpendingRuleCondition extends AbstractModel
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CartCombineFactory $cartCombineFactory
     * @param CollectionFactory $actionCollectionFactory
     * @param SpendingRuleResource $resource
     * @param SpendingRuleCollection $resourceCollection
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        private readonly CartCombineFactory $cartCombineFactory,
        private readonly CollectionFactory $actionCollectionFactory,
        SpendingRuleResource $resource,
        SpendingRuleCollection $resourceCollection,
        array $data = [],
        ?Json $serializer = null,
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data,
            $serializer,
        );
    }

    /**
     * Get conditions instance
     *
     * @return CartCombine
     */
    public function getConditionsInstance(): CartCombine
    {
        return $this->cartCombineFactory->create();
    }

    /**
     * Get actions instance
     *
     * @return Collection
     */
    public function getActionsInstance(): Collection
    {
        return $this->actionCollectionFactory->create();
    }

    /**
     * Get conditions fieldset ID for UI component forms
     *
     * @param string $formName
     * @return string
     */
    public function getConditionsFieldSetId(string $formName = ''): string
    {
        return $formName . 'rule_conditions_fieldset_' . (string) $this->getId();
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SpendingRuleResource::class);
    }
}
