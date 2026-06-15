<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;

/**
 * Reward Points Transaction Model
 */
class Transaction extends AbstractModel implements TransactionInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TransactionResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransactionId(): ?int
    {
        return $this->getData(self::TRANSACTION_ID) ? (int) $this->getData(self::TRANSACTION_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setTransactionId(int $transactionId): static
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountId(): int
    {
        return (int) $this->getData(self::ACCOUNT_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setAccountId(int $accountId): static
    {
        return $this->setData(self::ACCOUNT_ID, $accountId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerId(int $customerId): static
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId(): ?int
    {
        return $this->getData(self::STORE_ID) ? (int) $this->getData(self::STORE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreId(?int $storeId): static
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * {@inheritdoc}
     */
    public function getPointsDelta(): int
    {
        return (int) $this->getData(self::POINTS_DELTA);
    }

    /**
     * {@inheritdoc}
     */
    public function setPointsDelta(int $delta): static
    {
        return $this->setData(self::POINTS_DELTA, $delta);
    }

    /**
     * {@inheritdoc}
     */
    public function getPointsBalanceAfter(): int
    {
        return (int) $this->getData(self::POINTS_BALANCE_AFTER);
    }

    /**
     * {@inheritdoc}
     */
    public function setPointsBalanceAfter(int $balance): static
    {
        return $this->setData(self::POINTS_BALANCE_AFTER, $balance);
    }

    /**
     * {@inheritdoc}
     */
    public function getActionCode(): string
    {
        return (string) $this->getData(self::ACTION_CODE);
    }

    /**
     * {@inheritdoc}
     */
    public function setActionCode(string $actionCode): static
    {
        return $this->setData(self::ACTION_CODE, $actionCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(string $status): static
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * {@inheritdoc}
     */
    public function getComment(): ?string
    {
        return $this->getData(self::COMMENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setComment(?string $comment): static
    {
        return $this->setData(self::COMMENT, $comment);
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiresAt(): ?string
    {
        return $this->getData(self::EXPIRES_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setExpiresAt(?string $expiresAt): static
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) ? (int) $this->getData(self::ORDER_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderId(?int $orderId): static
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreditmemoId(): ?int
    {
        return $this->getData(self::CREDITMEMO_ID) ? (int) $this->getData(self::CREDITMEMO_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreditmemoId(?int $creditmemoId): static
    {
        return $this->setData(self::CREDITMEMO_ID, $creditmemoId);
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleId(): ?int
    {
        return $this->getData(self::RULE_ID) ? (int) $this->getData(self::RULE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setRuleId(?int $ruleId): static
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleType(): ?string
    {
        return $this->getData(self::RULE_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function setRuleType(?string $ruleType): static
    {
        return $this->setData(self::RULE_TYPE, $ruleType);
    }

    /**
     * {@inheritdoc}
     */
    public function getAdminUserId(): ?int
    {
        return $this->getData(self::ADMIN_USER_ID) ? (int) $this->getData(self::ADMIN_USER_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setAdminUserId(?int $adminUserId): static
    {
        return $this->setData(self::ADMIN_USER_ID, $adminUserId);
    }

    /**
     * {@inheritdoc}
     */
    public function getAdminUserName(): ?string
    {
        return $this->getData(self::ADMIN_USER_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdminUserName(?string $adminUserName): static
    {
        return $this->setData(self::ADMIN_USER_NAME, $adminUserName);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }
}
