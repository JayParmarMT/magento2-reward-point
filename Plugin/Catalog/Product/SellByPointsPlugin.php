<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Catalog\Product;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin on Product::getPrice to add "buy with points" data via extension attributes
 */
class SellByPointsPlugin
{
    /**
     * @param CustomerSession $customerSession
     * @param BalanceManagementInterface $balanceManagement
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * After getPrice — tag product with sell-by-points display data if applicable
     *
     * @param Product $subject
     * @param float|null $result
     * @return float|null
     */
    public function afterGetPrice(Product $subject, ?float $result): ?float
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        $pointPrice = (int) ($subject->getData('meetanshi_rp_point_price') ?? 0);

        if ($pointPrice <= 0) {
            return $result;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $balance = $this->balanceManagement->getBalance($customerId, $websiteId);

            if ($balance >= $pointPrice) {
                // Flag the product so the BuyWithPoints block knows to render
                $subject->setData('meetanshi_rp_can_buy_with_points', true);
                $subject->setData('meetanshi_rp_customer_balance', $balance);
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                'RewardPoints: SellByPointsPlugin - could not check balance',
                ['product_id' => $subject->getId(), 'exception' => $e->getMessage()],
            );
        }

        return $result;
    }
}
