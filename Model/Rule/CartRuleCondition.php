<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rule\Model\AbstractModel;
use Magento\SalesRule\Model\Rule\Condition\Product\CombineFactory as CartActionCombineFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule as CartRuleResource;
use Meetanshi\RewardPoints\Model\Rule\Condition\CartCombine;
use Meetanshi\RewardPoints\Model\Rule\Condition\CartCombineFactory;

/**
 * Cart Rule Condition model — extends AbstractModel for conditions/actions tree support
 */
class CartRuleCondition extends AbstractModel
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param CartCombineFactory $cartCombineFactory
     * @param CartActionCombineFactory $actionCombineFactory
     * @param CartRuleResource $resource
     * @param \Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\Collection $resourceCollection
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        private readonly CartCombineFactory $cartCombineFactory,
        private readonly CartActionCombineFactory $actionCombineFactory,
        CartRuleResource $resource,
        \Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\Collection $resourceCollection,
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
     * Get conditions instance (CartCombine)
     *
     * @return CartCombine
     */
    public function getConditionsInstance(): CartCombine
    {
        return $this->cartCombineFactory->create();
    }

    /**
     * Get actions instance (SalesRule product combine — for "apply to matching items")
     *
     * @return \Magento\SalesRule\Model\Rule\Condition\Product\Combine
     */
    public function getActionsInstance(): \Magento\SalesRule\Model\Rule\Condition\Product\Combine
    {
        return $this->actionCombineFactory->create();
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
        $this->_init(CartRuleResource::class);
    }
}
