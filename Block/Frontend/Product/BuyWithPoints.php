<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Block/ViewModel for "Buy With Points" on Product Detail Page
 */
class BuyWithPoints extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param CustomerSession $customerSession
     * @param BalanceManagementInterface $balanceManagement
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CustomerSession $customerSession,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get point price for current product (null if not set or zero)
     *
     * @return int|null
     */
    public function getPointPrice(): ?int
    {
        $product = $this->registry->registry('current_product');

        if (!$product) {
            return null;
        }

        $pointPrice = $product->getData('meetanshi_rp_point_price');

        if ($pointPrice === null || $pointPrice === '' || (int) $pointPrice <= 0) {
            return null;
        }

        return (int) $pointPrice;
    }

    /**
     * Check if current customer can buy with points
     *
     * @return bool
     */
    public function canBuyWithPoints(): bool
    {
        $pointPrice = $this->getPointPrice();

        if ($pointPrice === null) {
            return false;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        if (!$this->config->isEnabled()) {
            return false;
        }

        $balance = $this->getCustomerBalance();

        return $balance >= $pointPrice;
    }

    /**
     * Get current customer's point balance
     *
     * @return int
     */
    public function getCustomerBalance(): int
    {
        if (!$this->customerSession->isLoggedIn()) {
            return 0;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();

            return $this->balanceManagement->getBalance($customerId, $websiteId);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: BuyWithPoints - getCustomerBalance failed',
                ['exception' => $e],
            );

            return 0;
        }
    }

    /**
     * Get the "buy with points" action URL
     *
     * @return string
     */
    public function getBuyWithPointsUrl(): string
    {
        $product = $this->registry->registry('current_product');

        if (!$product) {
            return '';
        }

        return $this->getUrl('rewardpoints/cart/addBuyWithPoints', ['product' => $product->getId()]);
    }
}
