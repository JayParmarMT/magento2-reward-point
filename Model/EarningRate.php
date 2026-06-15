<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate as EarningRateResource;

/**
 * Reward Points Earning Rate Model
 */
class EarningRate extends AbstractModel implements EarningRateInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(EarningRateResource::class);
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
    public function getMoneyStep(): float
    {
        return (float) $this->getData(self::MONEY_STEP);
    }

    /**
     * {@inheritdoc}
     */
    public function setMoneyStep(float $moneyStep): static
    {
        return $this->setData(self::MONEY_STEP, $moneyStep);
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
    public function getMinOrderTotal(): ?float
    {
        return $this->getData(self::MIN_ORDER_TOTAL) !== null
            ? (float) $this->getData(self::MIN_ORDER_TOTAL)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setMinOrderTotal(?float $minOrderTotal): static
    {
        return $this->setData(self::MIN_ORDER_TOTAL, $minOrderTotal);
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
