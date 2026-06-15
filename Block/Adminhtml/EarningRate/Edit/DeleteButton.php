<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\EarningRate\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Earning Rate Edit Delete Button
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
        $rateId = $this->getRateId();

        if (!$rateId) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this rate?'),
                $this->context->getUrlBuilder()->getUrl(
                    'meetanshi_rewardpoints/earningrate/delete',
                    ['rate_id' => $rateId],
                ),
            ),
            'sort_order' => 20,
        ];
    }

    /**
     * Get current rate ID from request
     *
     * @return int|null
     */
    private function getRateId(): ?int
    {
        $rateId = (int) $this->context->getRequest()->getParam('rate_id');

        return $rateId ?: null;
    }
}
