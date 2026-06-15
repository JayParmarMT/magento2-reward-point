<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\CatalogRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CatalogRule as CatalogRuleResource;
use Meetanshi\RewardPoints\Model\Rule\CatalogRuleFactory;

/**
 * Catalog Rule Repository
 */
class CatalogRuleRepository implements CatalogRuleRepositoryInterface
{
    /**
     * @param CatalogRuleResource $resource
     * @param CatalogRuleFactory $ruleFactory
     */
    public function __construct(
        private readonly CatalogRuleResource $resource,
        private readonly CatalogRuleFactory $ruleFactory,
    ) {
    }

    /**
     * Save catalog rule
     *
     * @param CatalogRule $rule
     * @return CatalogRule
     * @throws CouldNotSaveException
     */
    public function save(CatalogRule $rule): CatalogRule
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save Catalog Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return $rule;
    }

    /**
     * Get catalog rule by ID
     *
     * @param int $ruleId
     * @return CatalogRule
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): CatalogRule
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);

        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('Catalog Earning Rule with ID "%1" does not exist.', $ruleId),
            );
        }

        return $rule;
    }

    /**
     * Delete catalog rule
     *
     * @param CatalogRule $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CatalogRule $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete Catalog Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * Delete catalog rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool
    {
        return $this->delete($this->getById($ruleId));
    }
}
