<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin;

use Magento\Backend\Block\AnchorRenderer;
use Magento\Backend\Model\Menu\Item;

/**
 * Anchor Renderer Plugin.
 */

class AnchorRendererPlugin
{
    /**
     * Render section label (submenu-group-title) for items at any submenu level,
     * not just level 1. This allows "Earning" and "Spending" nodes—which sit at
     * level 2 inside "Reward Points"—to render as grey section headers instead
     * of clickable links.
     *
     * @param AnchorRenderer $subject
     * @param callable $proceed
     * @param Item|false $activeItem
     * @param Item $menuItem
     * @param int $level
     * @return string
     */
    public function aroundRenderAnchor(
        AnchorRenderer $subject,
        callable $proceed,
        $activeItem,
        Item $menuItem,
        int $level,
    ): string {
        if ($level >= 1 && $menuItem->getUrl() === '#' && $menuItem->hasChildren()) {
            return '<strong class="submenu-group-title" role="presentation">'
                . '<span>' . $menuItem->getTitle() . '</span>'
                . '</strong>';
        }

        return $proceed($activeItem, $menuItem, $level);
    }
}
