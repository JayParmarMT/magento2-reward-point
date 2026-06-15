<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\ReferralRule\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Referral Rule Edit Delete Button
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
        $ruleId = $this->getRuleId();

        if (!$ruleId) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this rule?'),
                $this->context->getUrlBuilder()->getUrl(
                    'meetanshi_rewardpoints/referralrule/delete',
                    ['rule_id' => $ruleId],
                ),
            ),
            'sort_order' => 20,
        ];
    }

    /**
     * Get current rule ID from request
     *
     * @return int|null
     */
    private function getRuleId(): ?int
    {
        $ruleId = (int) $this->context->getRequest()->getParam('rule_id');

        return $ruleId ?: null;
    }
}
