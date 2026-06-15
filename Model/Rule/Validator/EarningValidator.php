<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Validator;

use Magento\Sales\Api\Data\OrderInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Validates whether a customer/order is eligible to earn reward points
 */
class EarningValidator
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * Check if the order is eligible to earn reward points
     *
     * @param OrderInterface $order
     * @param int $customerId
     * @return bool
     */
    public function isEligible(OrderInterface $order, int $customerId): bool
    {
        if (!$this->config->isEnabled((int) $order->getStoreId())) {
            return false;
        }

        if ($customerId <= 0) {
            return false;
        }

        $excludedStatuses = ['canceled', 'closed', 'holded'];

        if (in_array($order->getStatus(), $excludedStatuses, true)) {
            return false;
        }

        if (!$this->config->isEarnFromCouponOrders((int) $order->getStoreId())) {
            if ($order->getCouponCode()) {
                return false;
            }
        }

        return true;
    }
}
