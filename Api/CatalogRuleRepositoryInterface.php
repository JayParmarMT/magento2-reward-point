<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Meetanshi\RewardPoints\Model\Rule\CatalogRule;

/**
 * Catalog Earning Rule Repository Interface
 *
 * @api
 */
interface CatalogRuleRepositoryInterface
{
    /**
     * Save catalog rule
     *
     * @param CatalogRule $rule
     * @return CatalogRule
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(CatalogRule $rule): CatalogRule;

    /**
     * Get catalog rule by ID
     *
     * @param int $ruleId
     * @return CatalogRule
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $ruleId): CatalogRule;

    /**
     * Delete catalog rule
     *
     * @param CatalogRule $rule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(CatalogRule $rule): bool;

    /**
     * Delete catalog rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool;
}
