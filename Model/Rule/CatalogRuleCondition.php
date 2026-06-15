<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Magento\Rule\Model\Action\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CatalogRule as CatalogRuleResource;
use Meetanshi\RewardPoints\Model\Rule\Condition\CatalogCombine;
use Meetanshi\RewardPoints\Model\Rule\Condition\CatalogCombineFactory;

/**
 * Catalog Rule Condition model — extends AbstractModel for conditions/actions tree support
 */
class CatalogRuleCondition extends AbstractModel
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param CatalogCombineFactory $catalogCombineFactory
     * @param CollectionFactory $actionCollectionFactory
     * @param CatalogRuleResource $resource
     * @param \Meetanshi\RewardPoints\Model\ResourceModel\Rule\CatalogRule\Collection $resourceCollection
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        private readonly CatalogCombineFactory $catalogCombineFactory,
        private readonly CollectionFactory $actionCollectionFactory,
        CatalogRuleResource $resource,
        \Meetanshi\RewardPoints\Model\ResourceModel\Rule\CatalogRule\Collection $resourceCollection,
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
     * Get conditions instance (CatalogCombine)
     *
     * @return CatalogCombine
     */
    public function getConditionsInstance(): CatalogCombine
    {
        return $this->catalogCombineFactory->create();
    }

    /**
     * Get actions instance (empty Collection — catalog rules have no "apply to items" actions)
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
        $this->_init(CatalogRuleResource::class);
    }
}
