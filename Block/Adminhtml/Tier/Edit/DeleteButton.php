<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Tier\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Tier Edit Delete Button
 */
class DeleteButton implements ButtonProviderInterface
{
    /**
     * @param Context $context
     */
    public function __construct(
        private readonly Context $context,
    ) {
    }

    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $tierId = $this->getTierId();

        if (!$tierId) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this tier?'),
                $this->context->getUrlBuilder()->getUrl(
                    'meetanshi_rewardpoints/tier/delete',
                    ['tier_id' => $tierId],
                ),
            ),
            'sort_order' => 20,
        ];
    }

    /**
     * Get current tier ID from request
     *
     * @return int|null
     */
    private function getTierId(): ?int
    {
        $tierId = (int) $this->context->getRequest()->getParam('tier_id');

        return $tierId ?: null;
    }
}
