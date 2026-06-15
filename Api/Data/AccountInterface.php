<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Account Data Interface
 *
 * @api
 */
interface AccountInterface
{
    public const ACCOUNT_ID = 'account_id';
    public const CUSTOMER_ID = 'customer_id';
    public const WEBSITE_ID = 'website_id';
    public const POINTS_BALANCE = 'points_balance';
    public const TOTAL_EARNED = 'total_earned';
    public const TOTAL_SPENT = 'total_spent';
    public const IS_ENABLED = 'is_enabled';
    public const IS_SUBSCRIBED_BALANCE = 'is_subscribed_balance';
    public const IS_SUBSCRIBED_EXPIRATION = 'is_subscribed_expiration';
    public const CURRENT_TIER_ID = 'current_tier_id';
    public const LIFETIME_INVOICE_AMOUNT = 'lifetime_invoice_amount';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getAccountId(): ?int;

    /**
     * @param int $accountId
     * @return $this
     */
    public function setAccountId(int $accountId): static;

    /**
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): static;

    /**
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId(int $websiteId): static;

    /**
     * @return int
     */
    public function getPointsBalance(): int;

    /**
     * @param int $balance
     * @return $this
     */
    public function setPointsBalance(int $balance): static;

    /**
     * @return int
     */
    public function getTotalEarned(): int;

    /**
     * @param int $totalEarned
     * @return $this
     */
    public function setTotalEarned(int $totalEarned): static;

    /**
     * @return int
     */
    public function getTotalSpent(): int;

    /**
     * @param int $totalSpent
     * @return $this
     */
    public function setTotalSpent(int $totalSpent): static;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @param bool $isEnabled
     * @return $this
     */
    public function setIsEnabled(bool $isEnabled): static;

    /**
     * @return bool
     */
    public function isSubscribedBalance(): bool;

    /**
     * @param bool $subscribed
     * @return $this
     */
    public function setIsSubscribedBalance(bool $subscribed): static;

    /**
     * @return bool
     */
    public function isSubscribedExpiration(): bool;

    /**
     * @param bool $subscribed
     * @return $this
     */
    public function setIsSubscribedExpiration(bool $subscribed): static;

    /**
     * @return int|null
     */
    public function getCurrentTierId(): ?int;

    /**
     * @param int|null $tierId
     * @return $this
     */
    public function setCurrentTierId(?int $tierId): static;

    /**
     * @return float
     */
    public function getLifetimeInvoiceAmount(): float;

    /**
     * @param float $amount
     * @return $this
     */
    public function setLifetimeInvoiceAmount(float $amount): static;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
