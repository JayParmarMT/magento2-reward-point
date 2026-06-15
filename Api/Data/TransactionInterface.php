<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Transaction Data Interface
 *
 * @api
 */
interface TransactionInterface
{
    public const TRANSACTION_ID = 'transaction_id';
    public const ACCOUNT_ID = 'account_id';
    public const CUSTOMER_ID = 'customer_id';
    public const STORE_ID = 'store_id';
    public const POINTS_DELTA = 'points_delta';
    public const POINTS_BALANCE_AFTER = 'points_balance_after';
    public const ACTION_CODE = 'action_code';
    public const STATUS = 'status';
    public const COMMENT = 'comment';
    public const EXPIRES_AT = 'expires_at';
    public const ORDER_ID = 'order_id';
    public const CREDITMEMO_ID = 'creditmemo_id';
    public const RULE_ID = 'rule_id';
    public const RULE_TYPE = 'rule_type';
    public const ADMIN_USER_ID = 'admin_user_id';
    public const ADMIN_USER_NAME = 'admin_user_name';
    public const CREATED_AT = 'created_at';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    // Action code constants
    public const ACTION_EARN_ORDER = 'earn_order';
    public const ACTION_SPEND_ORDER = 'spend_order';
    public const ACTION_REFUND_EARN = 'refund_earn';
    public const ACTION_REFUND_SPEND = 'refund_spend';
    public const ACTION_EXPIRE = 'expire';
    public const ACTION_ADMIN = 'admin';
    public const ACTION_SIGNUP = 'signup';
    public const ACTION_BIRTHDAY = 'birthday';
    public const ACTION_REVIEW = 'review';
    public const ACTION_NEWSLETTER = 'newsletter';
    public const ACTION_REFER_SIGNUP = 'refer_signup';
    public const ACTION_REFER_ORDER = 'refer_order';
    public const ACTION_INACTIVITY = 'inactivity';
    public const ACTION_ALLOCATION = 'allocation';
    public const ACTION_TIER_UP = 'tier_up';
    public const ACTION_TIER_DOWN = 'tier_down';
    public const ACTION_REFUND_TIER_BONUS = 'refund_tier_bonus';

    /**
     * @return int|null
     */
    public function getTransactionId(): ?int;

    /**
     * @param int $transactionId
     * @return $this
     */
    public function setTransactionId(int $transactionId): static;

    /**
     * @return int
     */
    public function getAccountId(): int;

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
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): static;

    /**
     * @return int
     */
    public function getPointsDelta(): int;

    /**
     * @param int $delta
     * @return $this
     */
    public function setPointsDelta(int $delta): static;

    /**
     * @return int
     */
    public function getPointsBalanceAfter(): int;

    /**
     * @param int $balance
     * @return $this
     */
    public function setPointsBalanceAfter(int $balance): static;

    /**
     * @return string
     */
    public function getActionCode(): string;

    /**
     * @param string $actionCode
     * @return $this
     */
    public function setActionCode(string $actionCode): static;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): static;

    /**
     * @return string|null
     */
    public function getComment(): ?string;

    /**
     * @param string|null $comment
     * @return $this
     */
    public function setComment(?string $comment): static;

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * @param string|null $expiresAt
     * @return $this
     */
    public function setExpiresAt(?string $expiresAt): static;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): static;

    /**
     * @return int|null
     */
    public function getCreditmemoId(): ?int;

    /**
     * @param int|null $creditmemoId
     * @return $this
     */
    public function setCreditmemoId(?int $creditmemoId): static;

    /**
     * @return int|null
     */
    public function getRuleId(): ?int;

    /**
     * @param int|null $ruleId
     * @return $this
     */
    public function setRuleId(?int $ruleId): static;

    /**
     * @return string|null
     */
    public function getRuleType(): ?string;

    /**
     * @param string|null $ruleType
     * @return $this
     */
    public function setRuleType(?string $ruleType): static;

    /**
     * @return int|null
     */
    public function getAdminUserId(): ?int;

    /**
     * @param int|null $adminUserId
     * @return $this
     */
    public function setAdminUserId(?int $adminUserId): static;

    /**
     * @return string|null
     */
    public function getAdminUserName(): ?string;

    /**
     * @param string|null $adminUserName
     * @return $this
     */
    public function setAdminUserName(?string $adminUserName): static;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
