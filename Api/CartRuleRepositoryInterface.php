<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Meetanshi\RewardPoints\Model\Rule\CartRule;

/**
 * Cart Earning Rule Repository Interface
 *
 * @api
 */
interface CartRuleRepositoryInterface
{
    /**
     * Save cart rule
     *
     * @param CartRule $rule
     * @return CartRule
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(CartRule $rule): CartRule;

    /**
     * Get cart rule by ID
     *
     * @param int $ruleId
     * @return CartRule
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $ruleId): CartRule;

    /**
     * Delete cart rule
     *
     * @param CartRule $rule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(CartRule $rule): bool;

    /**
     * Delete cart rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool;
}
