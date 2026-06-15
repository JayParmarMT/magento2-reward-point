<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;

/**
 * Transaction Status source model
 */
class TransactionStatus implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => TransactionInterface::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => TransactionInterface::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => TransactionInterface::STATUS_EXPIRED, 'label' => __('Expired')],
            ['value' => TransactionInterface::STATUS_CANCELLED, 'label' => __('Cancelled')],
        ];
    }

    /**
     * Get options as key-value array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            TransactionInterface::STATUS_PENDING => __('Pending'),
            TransactionInterface::STATUS_ACTIVE => __('Active'),
            TransactionInterface::STATUS_EXPIRED => __('Expired'),
            TransactionInterface::STATUS_CANCELLED => __('Cancelled'),
        ];
    }
}
