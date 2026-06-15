<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;

/**
 * Reward Points Account Model
 */
class Account extends AbstractModel implements AccountInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(AccountResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountId(): ?int
    {
        return $this->getData(self::ACCOUNT_ID) ? (int) $this->getData(self::ACCOUNT_ID) : null;
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
    public function getWebsiteId(): int
    {
        return (int) $this->getData(self::WEBSITE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setWebsiteId(int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * {@inheritdoc}
     */
    public function getPointsBalance(): int
    {
        return (int) $this->getData(self::POINTS_BALANCE);
    }

    /**
     * {@inheritdoc}
     */
    public function setPointsBalance(int $balance): static
    {
        return $this->setData(self::POINTS_BALANCE, $balance);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalEarned(): int
    {
        return (int) $this->getData(self::TOTAL_EARNED);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalEarned(int $totalEarned): static
    {
        return $this->setData(self::TOTAL_EARNED, $totalEarned);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalSpent(): int
    {
        return (int) $this->getData(self::TOTAL_SPENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalSpent(int $totalSpent): static
    {
        return $this->setData(self::TOTAL_SPENT, $totalSpent);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getData(self::IS_ENABLED);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsEnabled(bool $isEnabled): static
    {
        return $this->setData(self::IS_ENABLED, (int) $isEnabled);
    }

    /**
     * {@inheritdoc}
     */
    public function isSubscribedBalance(): bool
    {
        return (bool) $this->getData(self::IS_SUBSCRIBED_BALANCE);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsSubscribedBalance(bool $subscribed): static
    {
        return $this->setData(self::IS_SUBSCRIBED_BALANCE, (int) $subscribed);
    }

    /**
     * {@inheritdoc}
     */
    public function isSubscribedExpiration(): bool
    {
        return (bool) $this->getData(self::IS_SUBSCRIBED_EXPIRATION);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsSubscribedExpiration(bool $subscribed): static
    {
        return $this->setData(self::IS_SUBSCRIBED_EXPIRATION, (int) $subscribed);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentTierId(): ?int
    {
        return $this->getData(self::CURRENT_TIER_ID) ? (int) $this->getData(self::CURRENT_TIER_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentTierId(?int $tierId): static
    {
        return $this->setData(self::CURRENT_TIER_ID, $tierId);
    }

    /**
     * {@inheritdoc}
     */
    public function getLifetimeInvoiceAmount(): float
    {
        return (float) $this->getData(self::LIFETIME_INVOICE_AMOUNT);
    }

    /**
     * {@inheritdoc}
     */
    public function setLifetimeInvoiceAmount(float $amount): static
    {
        return $this->setData(self::LIFETIME_INVOICE_AMOUNT, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
