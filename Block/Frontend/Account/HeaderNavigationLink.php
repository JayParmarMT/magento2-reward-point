<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Account;

use Hyva\Theme\Block\SortableItem;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Hyvä header dropdown navigation link for Reward Points.
 *
 * Extends Hyva\Theme\Block\SortableItem so it integrates correctly with the
 * header.customer.logged.in.links sorted list (sort_order argument, default
 * link template Hyva_Theme::sortable-item/link.phtml).
 *
 * Returns empty output when the Reward Points module is disabled in admin
 * (Stores → Configuration → Reward Points → General → Enable Module).
 */
class HeaderNavigationLink extends SortableItem
{
    /**
     * @param Context $context
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return empty string when module is disabled in admin config.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}
