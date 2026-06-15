<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Spending Rate Data Interface
 *
 * @api
 */
interface SpendingRateInterface
{
    public const RATE_ID = 'rate_id';
    public const CUSTOMER_GROUP_IDS = 'customer_group_ids';
    public const WEBSITE_ID = 'website_id';
    public const POINTS = 'points';
    public const CURRENCY_AMOUNT = 'currency_amount';
    public const MIN_POINTS_PER_ORDER = 'min_points_per_order';
    public const PRIORITY = 'priority';
    public const IS_ACTIVE = 'is_active';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get rate ID
     *
     * @return int|null
     */
    public function getRateId(): ?int;

    /**
     * Set rate ID
     *
     * @param int $rateId
     * @return static
     */
    public function setRateId(int $rateId): static;

    /**
     * Get customer group IDs
     *
     * @return string|null
     */
    public function getCustomerGroupIds(): ?string;

    /**
     * Set customer group IDs
     *
     * @param string|null $customerGroupIds
     * @return static
     */
    public function setCustomerGroupIds(?string $customerGroupIds): static;

    /**
     * Get website ID
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * Set website ID
     *
     * @param int|null $websiteId
     * @return static
     */
    public function setWebsiteId(?int $websiteId): static;

    /**
     * Get points
     *
     * @return int
     */
    public function getPoints(): int;

    /**
     * Set points
     *
     * @param int $points
     * @return static
     */
    public function setPoints(int $points): static;

    /**
     * Get currency amount
     *
     * @return float
     */
    public function getCurrencyAmount(): float;

    /**
     * Set currency amount
     *
     * @param float $currencyAmount
     * @return static
     */
    public function setCurrencyAmount(float $currencyAmount): static;

    /**
     * Get minimum points per order
     *
     * @return int
     */
    public function getMinPointsPerOrder(): int;

    /**
     * Set minimum points per order
     *
     * @param int $minPoints
     * @return static
     */
    public function setMinPointsPerOrder(int $minPoints): static;

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set priority
     *
     * @param int $priority
     * @return static
     */
    public function setPriority(int $priority): static;

    /**
     * Is active
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Set is active
     *
     * @param bool $isActive
     * @return static
     */
    public function setIsActive(bool $isActive): static;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
