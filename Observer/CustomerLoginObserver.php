<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Customer login observer — updates last login timestamp for inactivity tracking
 */
class CustomerLoginObserver implements ObserverInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param TimezoneInterface $timezone
     * @param Config $config
     * @param ResponseFactory $responseFactory
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        private readonly ResponseFactory $responseFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $customerTable = $this->resourceConnection->getTableName('customer_entity');
            $nowUtc = $this->timezone->date()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $connection->update(
                $customerTable,
                ['updated_at' => $nowUtc],
                ['entity_id = ?' => (int) $customer->getId()],
            );

            // Redirect to Reward Points dashboard after login if configured
            if ($this->config->isRedirectAfterLogin()) {
                $url = $this->urlBuilder->getUrl('rewardpoints/account');
                $this->responseFactory->create()
                    ->setRedirect($url)
                    ->sendResponse();
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    '[RewardPoints] CustomerLoginObserver error for customer %d: %s',
                    (int) $customer->getId(),
                    $e->getMessage(),
                ),
            );
        }
    }
}
