<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;

/**
 * Invitation Status source model
 */
class InvitationStatus implements OptionSourceInterface
{
    /**
     * Get options array for use in select fields / grid filters
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => InvitationInterface::STATUS_PENDING,   'label' => __('Pending')],
            ['value' => InvitationInterface::STATUS_SIGNED_UP, 'label' => __('Signed Up')],
            ['value' => InvitationInterface::STATUS_COMPLETED, 'label' => __('Completed')],
            ['value' => InvitationInterface::STATUS_CANCELLED, 'label' => __('Cancelled')],
        ];
    }

    /**
     * Get options as a flat key → label array
     *
     * @return array<string, \Magento\Framework\Phrase>
     */
    public function toArray(): array
    {
        return [
            InvitationInterface::STATUS_PENDING   => __('Pending'),
            InvitationInterface::STATUS_SIGNED_UP => __('Signed Up'),
            InvitationInterface::STATUS_COMPLETED => __('Completed'),
            InvitationInterface::STATUS_CANCELLED => __('Cancelled'),
        ];
    }
}
