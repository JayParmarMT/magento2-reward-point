<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Provides reward points data for the "meetanshi-rewardpoints" customer section.
 *
 * This data is loaded by Magento's customer-data JS module and cached in
 * local storage. It is invalidated whenever rewardpoints/account/saveSettings
 * is posted (see etc/frontend/sections.xml).
 */
class RewardPoints implements SectionSourceInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns section data consumed by customer-data JS.
     *
     * @return array<string, mixed>
     */
    public function getSectionData(): array
    {
        if (!$this->config->isEnabled()) {
            return [
                'enabled'  => false,
                'balance'  => 0,
                'label'    => '',
            ];
        }

        $balance   = 0;
        $isEnabled = false;

        if ($this->customerSession->isLoggedIn()) {
            try {
                $customerId = (int) $this->customerSession->getCustomerId();
                $websiteId  = (int) $this->storeManager->getWebsite()->getId();
                $account    = $this->accountRepository->getByCustomer($customerId, $websiteId);
                $balance    = $account->getPointsBalance();
                $isEnabled  = $account->isEnabled();
            } catch (\Exception $e) {
                $this->logger->warning(
                    'RewardPoints CustomerData: failed to load account',
                    ['exception' => $e],
                );
            }
        }

        return [
            'enabled'     => $isEnabled,
            'balance'     => $balance,
            'label'       => $this->config->formatPoints($balance),
            'point_label' => $balance === 1
                ? $this->config->getPointLabel()
                : $this->config->getPointLabelPlural(),
        ];
    }
}
