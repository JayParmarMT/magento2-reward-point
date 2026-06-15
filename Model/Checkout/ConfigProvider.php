<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Provides reward points configuration for the checkout JS component.
 *
 * Data is exposed under window.checkoutConfig.rewardPoints.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns reward points configuration for the checkout frontend
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if (!$this->config->isEnabled() || !$this->config->isShowOnCheckout()) {
            return ['rewardPoints' => ['enabled' => false]];
        }

        $balance = 0;

        if ($this->customerSession->isLoggedIn()) {
            try {
                $customerId = (int) $this->customerSession->getCustomerId();
                $websiteId  = (int) $this->storeManager->getWebsite()->getId();
                $account    = $this->accountRepository->getByCustomer($customerId, $websiteId);
                $balance    = $account->getPointsBalance();
            } catch (\Exception $e) {
                $this->logger->warning(
                    'RewardPoints: ConfigProvider - failed to load balance',
                    ['exception' => $e],
                );
            }
        }

        $minPoints = $this->config->getMinSpendingPoints();
        $configMax = $this->config->getMaxSpendingPoints();
        $maxPoints = $configMax > 0 ? min($balance, $configMax) : $balance;

        // Read already-applied points & discount from the active quote
        // so the checkout component can restore the applied state on page load.
        $pointsApplied  = 0;
        $discountAmount = 0.0;

        try {
            $quote          = $this->checkoutSession->getQuote();
            $pointsApplied  = (int) $quote->getData('reward_points_used');
            $discountAmount = (float) $quote->getData('reward_points_discount');
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: ConfigProvider - failed to load quote points',
                ['exception' => $e],
            );
        }

        return [
            'rewardPoints' => [
                'enabled'        => true,
                'balance'        => $balance,
                'minPoints'      => $minPoints,
                'maxPoints'      => $maxPoints,
                'useMaxDefault'  => $this->config->isUseMaxByDefault(),
                'pointsApplied'  => $pointsApplied,
                'discountAmount' => $discountAmount,
                'applyUrl'       => $this->urlBuilder->getUrl('rewardpoints/cart/apply'),
                'removeUrl'      => $this->urlBuilder->getUrl('rewardpoints/cart/remove'),
            ],
        ];
    }
}
