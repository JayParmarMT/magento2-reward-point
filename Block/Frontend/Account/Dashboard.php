<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Account;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\ViewModel\Account\Dashboard as DashboardViewModel;

/**
 * Account dashboard block.
 *
 * Acts as a thin delegate so both the Luma template (which uses the injected
 * ViewModel via getData('view_model')) and the Hyvä template (which calls
 * methods directly on $block) work correctly.
 */
class Dashboard extends Template
{
    /**
     * @param Context $context
     * @param DashboardViewModel $viewModel
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly DashboardViewModel $viewModel,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the dashboard view model (used by Luma template via getData('view_model')).
     *
     * @return DashboardViewModel
     */
    public function getViewModel(): DashboardViewModel
    {
        return $this->viewModel;
    }

    // =========================================================================
    // Delegate methods — used directly by the Hyvä dashboard template
    // =========================================================================

    /**
     * Is the Reward Points module enabled?
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get the reward points account for the current customer.
     *
     * @return AccountInterface|null
     */
    public function getAccount(): ?AccountInterface
    {
        return $this->viewModel->getAccount();
    }

    /**
     * Get the current points balance.
     *
     * @return int
     */
    public function getBalance(): int
    {
        return $this->viewModel->getPointsBalance();
    }

    /**
     * Get total points ever earned.
     *
     * @return int
     */
    public function getTotalEarned(): int
    {
        return $this->viewModel->getTotalEarned();
    }

    /**
     * Get total points ever spent.
     *
     * @return int
     */
    public function getTotalSpent(): int
    {
        return $this->viewModel->getTotalSpent();
    }

    /**
     * Get the customer's current loyalty tier.
     *
     * @return TierInterface|null
     */
    public function getCurrentTier(): ?TierInterface
    {
        return $this->viewModel->getCurrentTier();
    }

    /**
     * Get recent transactions (last 5) for the current customer.
     *
     * @return TransactionInterface[]
     */
    public function getRecentTransactions(): array
    {
        return $this->viewModel->getRecentTransactions();
    }

    /**
     * Get the singular points label (e.g. "Point").
     *
     * @return string
     */
    public function getPointLabel(): string
    {
        return $this->config->getPointLabel();
    }

    /**
     * Get the plural points label (e.g. "Points").
     *
     * @return string
     */
    public function getPointLabelPlural(): string
    {
        return $this->config->getPointLabelPlural();
    }

    /**
     * Build the public media URL for a tier's badge image.
     *
     * Images are stored under pub/media/meetanshi/rewardpoints/tier/.
     *
     * @param TierInterface $tier
     * @return string
     */
    public function getTierImageUrl(TierInterface $tier): string
    {
        $image = $tier->getImage();

        if (empty($image)) {
            return '';
        }

        return $this->_storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        ) . 'meetanshi/rewardpoints/tier/' . ltrim($image, '/');
    }
}
