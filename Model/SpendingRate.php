<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate as SpendingRateResource;

/**
 * Reward Points Spending Rate Model
 */
class SpendingRate extends AbstractModel implements SpendingRateInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SpendingRateResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getRateId(): ?int
    {
        return $this->getData(self::RATE_ID) ? (int) $this->getData(self::RATE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setRateId(int $rateId): static
    {
        return $this->setData(self::RATE_ID, $rateId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerGroupIds(): ?string
    {
        return $this->getData(self::CUSTOMER_GROUP_IDS);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerGroupIds(?string $customerGroupIds): static
    {
        return $this->setData(self::CUSTOMER_GROUP_IDS, $customerGroupIds);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebsiteId(): ?int
    {
        return $this->getData(self::WEBSITE_ID) ? (int) $this->getData(self::WEBSITE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setWebsiteId(?int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * {@inheritdoc}
     */
    public function getPoints(): int
    {
        return (int) $this->getData(self::POINTS);
    }

    /**
     * {@inheritdoc}
     */
    public function setPoints(int $points): static
    {
        return $this->setData(self::POINTS, $points);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyAmount(): float
    {
        return (float) $this->getData(self::CURRENCY_AMOUNT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrencyAmount(float $currencyAmount): static
    {
        return $this->setData(self::CURRENCY_AMOUNT, $currencyAmount);
    }

    /**
     * {@inheritdoc}
     */
    public function getMinPointsPerOrder(): int
    {
        return (int) $this->getData(self::MIN_POINTS_PER_ORDER);
    }

    /**
     * {@inheritdoc}
     */
    public function setMinPointsPerOrder(int $minPoints): static
    {
        return $this->setData(self::MIN_POINTS_PER_ORDER, $minPoints);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    /**
     * {@inheritdoc}
     */
    public function setPriority(int $priority): static
    {
        return $this->setData(self::PRIORITY, $priority);
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
