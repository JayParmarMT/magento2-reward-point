<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Server-rendered reward points totals row for Hyvä cart and checkout.
 *
 * Replaces the KnockoutJS abstract-total component
 * (Meetanshi_RewardPoints/js/view/cart/totals/reward-points) used on Luma.
 * Hyvä renders cart and checkout totals via PHP blocks, not jsLayout components.
 */
class RewardPointsTotal extends Template
{
    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the totals row should be displayed.
     * Only shown when a non-zero reward points discount is applied.
     *
     * @return bool
     */
    public function isDisplayed(): bool
    {
        return $this->config->isEnabled() && $this->getDiscountAmount() > 0.0;
    }

    /**
     * Whether the module is enabled at all.
     *
     * Used by the Hyvä totals template as a lightweight guard — the Alpine
     * <template x-if> must always be rendered to the DOM so it can react to
     * the update-totals event after AJAX apply. We only skip output entirely
     * if the module itself is disabled.
     *
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Row title shown in totals table.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string) __('Reward Points Discount');
    }

    /**
     * Formatted discount value (negative, display currency).
     *
     * @return string
     */
    public function getFormattedValue(): string
    {
        $amount = $this->getDiscountAmount();

        if ($amount <= 0.0) {
            return '';
        }

        return '-' . $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
        );
    }

    /**
     * Raw discount amount from the active quote.
     *
     * @return float
     */
    private function getDiscountAmount(): float
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return (float) $quote->getData('reward_points_discount');
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
