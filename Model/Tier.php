<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier as TierResource;

/**
 * Reward Points Tier Model
 */
class Tier extends AbstractModel implements TierInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TierResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getTierId(): ?int
    {
        return $this->getData(self::TIER_ID) ? (int) $this->getData(self::TIER_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setTierId(int $tierId): static
    {
        return $this->setData(self::TIER_ID, $tierId);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): static
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription(?string $description): static
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsActive(bool $isActive): static
    {
        return $this->setData(self::IS_ACTIVE, (int) $isActive);
    }

    /**
     * {@inheritdoc}
     */
    public function getImage(): ?string
    {
        return $this->getData(self::IMAGE);
    }

    /**
     * {@inheritdoc}
     */
    public function setImage(?string $image): static
    {
        return $this->setData(self::IMAGE, $image);
    }

    /**
     * {@inheritdoc}
     */
    public function getMinPoints(): int
    {
        return (int) $this->getData(self::MIN_POINTS);
    }

    /**
     * {@inheritdoc}
     */
    public function setMinPoints(int $minPoints): static
    {
        return $this->setData(self::MIN_POINTS, $minPoints);
    }

    /**
     * {@inheritdoc}
     */
    public function getMinOrders(): int
    {
        return (int) $this->getData(self::MIN_ORDERS);
    }

    /**
     * {@inheritdoc}
     */
    public function setMinOrders(int $minOrders): static
    {
        return $this->setData(self::MIN_ORDERS, $minOrders);
    }

    /**
     * {@inheritdoc}
     */
    public function getEarningBonusPercent(): float
    {
        return (float) $this->getData(self::EARNING_BONUS_PERCENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setEarningBonusPercent(float $percent): static
    {
        return $this->setData(self::EARNING_BONUS_PERCENT, $percent);
    }

    /**
     * {@inheritdoc}
     */
    public function getBehaviorBonusPoints(): int
    {
        return (int) $this->getData(self::BEHAVIOR_BONUS_POINTS);
    }

    /**
     * {@inheritdoc}
     */
    public function setBehaviorBonusPoints(int $points): static
    {
        return $this->setData(self::BEHAVIOR_BONUS_POINTS, $points);
    }

    /**
     * {@inheritdoc}
     */
    public function getSpendingDiscountPercent(): float
    {
        return (float) $this->getData(self::SPENDING_DISCOUNT_PERCENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setSpendingDiscountPercent(float $percent): static
    {
        return $this->setData(self::SPENDING_DISCOUNT_PERCENT, $percent);
    }

    /**
     * {@inheritdoc}
     */
    public function isFreeShipping(): bool
    {
        return (bool) $this->getData(self::IS_FREE_SHIPPING);
    }

    /**
     * {@inheritdoc}
     */
    public function setFreeShipping(bool $freeShipping): static
    {
        return $this->setData(self::IS_FREE_SHIPPING, (int) $freeShipping);
    }

    /**
     * {@inheritdoc}
     */
    public function getLinkedCartRuleId(): ?int
    {
        return $this->getData(self::LINKED_CART_RULE_ID) ? (int) $this->getData(self::LINKED_CART_RULE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setLinkedCartRuleId(?int $cartRuleId): static
    {
        return $this->setData(self::LINKED_CART_RULE_ID, $cartRuleId);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailTemplate(): ?string
    {
        return $this->getData(self::EMAIL_TEMPLATE);
    }

    /**
     * {@inheritdoc}
     */
    public function setEmailTemplate(?string $emailTemplate): static
    {
        return $this->setData(self::EMAIL_TEMPLATE, $emailTemplate);
    }

    /**
     * {@inheritdoc}
     */
    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    /**
     * {@inheritdoc}
     */
    public function setSortOrder(int $sortOrder): static
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
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
