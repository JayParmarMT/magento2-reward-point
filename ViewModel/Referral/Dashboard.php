<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\ViewModel\Referral;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ReferralCodeGenerator;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation\CollectionFactory as InvitationCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Referral Dashboard ViewModel
 */
class Dashboard implements ArgumentInterface
{
    /**
     * @param CustomerSessionFactory $customerSessionFactory
     * @param StoreManagerInterface $storeManager
     * @param ReferralCodeGenerator $referralCodeGenerator
     * @param InvitationCollectionFactory $invitationCollectionFactory
     * @param UrlInterface $urlBuilder
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSessionFactory $customerSessionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReferralCodeGenerator $referralCodeGenerator,
        private readonly InvitationCollectionFactory $invitationCollectionFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Show Facebook share button?
     *
     * @return bool
     */
    public function isFacebookEnabled(): bool
    {
        return $this->config->isSocialFacebookEnabled();
    }

    /**
     * Show Twitter share button?
     *
     * @return bool
     */
    public function isTwitterEnabled(): bool
    {
        return $this->config->isSocialTwitterEnabled();
    }

    /**
     * Show Pinterest share button?
     *
     * @return bool
     */
    public function isPinterestEnabled(): bool
    {
        return $this->config->isSocialPinterestEnabled();
    }

    /**
     * Get the customer session instance.
     *
     * @return CustomerSession
     */
    private function getSession(): CustomerSession
    {
        return $this->customerSessionFactory->create();
    }

    /**
     * Resolve customer website ID safely.
     *
     * @return int
     */
    private function resolveWebsiteId(): int
    {
        $websiteId = (int) $this->getSession()->getCustomer()->getWebsiteId();

        if ($websiteId > 0) {
            return $websiteId;
        }

        try {
            return (int) $this->storeManager->getWebsite()->getId();
        } catch (\Exception) {
            return 1;
        }
    }

    /**
     * Get referral code for current customer
     *
     * @return string
     */
    public function getReferralCode(): string
    {
        $customerId = (int) $this->getSession()->getCustomerId();

        if ($customerId <= 0) {
            return '';
        }

        try {
            $websiteId = $this->resolveWebsiteId();

            return $this->referralCodeGenerator->getOrCreateCode($customerId, $websiteId);
        } catch (\Exception $e) {
            $this->logger->error('RewardPoints: Dashboard - getReferralCode failed', ['exception' => $e]);

            return '';
        }
    }

    /**
     * Get shareable referral URL
     *
     * @return string
     */
    public function getReferralUrl(): string
    {
        $code = $this->getReferralCode();

        if (empty($code)) {
            return '';
        }

        return $this->urlBuilder->getUrl('rewardpoints/referral/track', ['code' => $code]);
    }

    /**
     * Get all invitations sent by current customer
     *
     * @return InvitationInterface[]
     */
    public function getInvitations(): array
    {
        $customerId = (int) $this->getSession()->getCustomerId();

        if ($customerId <= 0) {
            return [];
        }

        $collection = $this->invitationCollectionFactory->create();
        $collection->addFieldToFilter('referrer_customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');

        return $collection->getItems();
    }

    /**
     * Get total invitations sent
     *
     * @return int
     */
    public function getSentCount(): int
    {
        return count($this->getInvitations());
    }

    /**
     * Get number of referrals that signed up
     *
     * @return int
     */
    public function getSignedUpCount(): int
    {
        $count = 0;

        foreach ($this->getInvitations() as $invitation) {
            if (in_array(
                $invitation->getStatus(),
                [InvitationInterface::STATUS_SIGNED_UP, InvitationInterface::STATUS_COMPLETED],
                true,
            )) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get number of referrals that placed an order
     *
     * @return int
     */
    public function getOrderedCount(): int
    {
        $count = 0;

        foreach ($this->getInvitations() as $invitation) {
            if ($invitation->getStatus() === InvitationInterface::STATUS_COMPLETED) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get total referrer points earned from all referrals
     *
     * @return int
     */
    public function getTotalReferrerPointsEarned(): int
    {
        $total = 0;

        foreach ($this->getInvitations() as $invitation) {
            $total += $invitation->getReferrerPointsEarned();
        }

        return $total;
    }
}
