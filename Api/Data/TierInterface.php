<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Tier Data Interface
 *
 * @api
 */
interface TierInterface
{
    public const TIER_ID = 'tier_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const IMAGE = 'image';
    public const MIN_POINTS = 'min_points';
    public const MIN_ORDERS = 'min_orders';
    public const EARNING_BONUS_PERCENT = 'earning_bonus_percent';
    public const BEHAVIOR_BONUS_POINTS = 'behavior_bonus_points';
    public const SPENDING_DISCOUNT_PERCENT = 'spending_discount_percent';
    public const IS_FREE_SHIPPING = 'is_free_shipping';
    public const LINKED_CART_RULE_ID = 'linked_cart_rule_id';
    public const EMAIL_TEMPLATE = 'email_template';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get tier ID
     *
     * @return int|null
     */
    public function getTierId(): ?int;

    /**
     * Set tier ID
     *
     * @param int $tierId
     * @return static
     */
    public function setTierId(int $tierId): static;

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set name
     *
     * @param string $name
     * @return static
     */
    public function setName(string $name): static;

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Set description
     *
     * @param string|null $description
     * @return static
     */
    public function setDescription(?string $description): static;

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
     * Get image
     *
     * @return string|null
     */
    public function getImage(): ?string;

    /**
     * Set image
     *
     * @param string|null $image
     * @return static
     */
    public function setImage(?string $image): static;

    /**
     * Get minimum points
     *
     * @return int
     */
    public function getMinPoints(): int;

    /**
     * Set minimum points
     *
     * @param int $minPoints
     * @return static
     */
    public function setMinPoints(int $minPoints): static;

    /**
     * Get minimum orders
     *
     * @return int
     */
    public function getMinOrders(): int;

    /**
     * Set minimum orders
     *
     * @param int $minOrders
     * @return static
     */
    public function setMinOrders(int $minOrders): static;

    /**
     * Get earning bonus percent
     *
     * @return float
     */
    public function getEarningBonusPercent(): float;

    /**
     * Set earning bonus percent
     *
     * @param float $percent
     * @return static
     */
    public function setEarningBonusPercent(float $percent): static;

    /**
     * Get behavior bonus points
     *
     * @return int
     */
    public function getBehaviorBonusPoints(): int;

    /**
     * Set behavior bonus points
     *
     * @param int $points
     * @return static
     */
    public function setBehaviorBonusPoints(int $points): static;

    /**
     * Get spending discount percent
     *
     * @return float
     */
    public function getSpendingDiscountPercent(): float;

    /**
     * Set spending discount percent
     *
     * @param float $percent
     * @return static
     */
    public function setSpendingDiscountPercent(float $percent): static;

    /**
     * Is free shipping
     *
     * @return bool
     */
    public function isFreeShipping(): bool;

    /**
     * Set free shipping
     *
     * @param bool $freeShipping
     * @return static
     */
    public function setFreeShipping(bool $freeShipping): static;

    /**
     * Get linked cart rule ID
     *
     * @return int|null
     */
    public function getLinkedCartRuleId(): ?int;

    /**
     * Set linked cart rule ID
     *
     * @param int|null $cartRuleId
     * @return static
     */
    public function setLinkedCartRuleId(?int $cartRuleId): static;

    /**
     * Get email template
     *
     * @return string|null
     */
    public function getEmailTemplate(): ?string;

    /**
     * Set email template
     *
     * @param string|null $emailTemplate
     * @return static
     */
    public function setEmailTemplate(?string $emailTemplate): static;

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return static
     */
    public function setSortOrder(int $sortOrder): static;

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
