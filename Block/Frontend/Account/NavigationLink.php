<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Account;

use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\DefaultPathInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Customer account navigation link for Reward Points pages.
 *
 * Extends the standard Link\Current so it inherits active-state highlighting.
 * Returns empty output when the Reward Points module is disabled in admin config,
 * making all three nav links (My Reward Points, Points History, My Referrals)
 * automatically disappear without any cache flush beyond the config cache.
 */
class NavigationLink extends Current
{
    /**
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * Return empty string when Reward Points module is disabled in admin config.
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
