<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation as InvitationResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation\CollectionFactory as InvitationCollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\ReferralRule\CollectionFactory as ReferralRuleCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\ReferralRuleConditionValidator;
use Psr\Log\LoggerInterface;

/**
 * Observer for sales order complete — awards referral points
 */
class SalesOrderCompleteObserver implements ObserverInterface
{
    /**
     * @param InvitationCollectionFactory $invitationCollectionFactory
     * @param InvitationResource $invitationResource
     * @param ReferralRuleCollectionFactory $referralRuleCollectionFactory
     * @param BalanceManagementInterface $balanceManagement
     * @param ReferralRuleConditionValidator $referralRuleConditionValidator
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly InvitationCollectionFactory $invitationCollectionFactory,
        private readonly InvitationResource $invitationResource,
        private readonly ReferralRuleCollectionFactory $referralRuleCollectionFactory,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly ReferralRuleConditionValidator $referralRuleConditionValidator,
        private readonly Config $config,
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

        /** @var Order $order */
        $order = $observer->getData('order');

        if (!$order instanceof Order || !$order->getEntityId()) {
            return;
        }

        if ($order->getStatus() !== Order::STATE_COMPLETE) {
            return;
        }

        $customerId = (int) $order->getCustomerId();

        if ($customerId <= 0) {
            return;
        }

        try {
            $websiteId = (int) $order->getStore()->getWebsiteId();
            $customerGroupId = (int) $order->getCustomerGroupId();
            $invitation = $this->loadSignedUpInvitation($customerId, $websiteId);

            if (!$invitation) {
                return;
            }

            // Idempotency: only process if this is the first completed order
            if (!$this->isFirstCompletedOrder($customerId, (int) $order->getEntityId())) {
                return;
            }

            $referralRule = $this->getActiveReferralRule($websiteId, $customerGroupId);

            if (!$referralRule) {
                return;
            }

            // Validate the referral rule's conditions against the triggering order
            if (!$this->referralRuleConditionValidator->ruleMatchesOrder(
                (int) $referralRule->getId(),
                $referralRule->getData('conditions_serialized'),
                $order,
            )) {
                return;
            }

            $referrerPoints = (int) $referralRule->getData('referrer_points');
            $refereePoints = (int) $referralRule->getData('referee_points');

            $orderId = (int) $order->getEntityId();
            $storeId = (int) $order->getStoreId();

            // Award referee points
            if ($refereePoints > 0) {
                $this->balanceManagement->addPoints(
                    $customerId,
                    $websiteId,
                    $refereePoints,
                    'referral_referee',
                    (string) __('Reward for completing first order via referral'),
                    null,
                    false,
                    [
                        'order_id' => $orderId,
                        'store_id' => $storeId,
                    ],
                );
            }

            // Award referrer points
            $referrerCustomerId = $invitation->getReferrerCustomerId();

            if ($referrerPoints > 0 && $referrerCustomerId > 0) {
                $this->balanceManagement->addPoints(
                    $referrerCustomerId,
                    $websiteId,
                    $referrerPoints,
                    'referral_referrer',
                    (string) __('Reward for successful referral (order #%1)', $order->getIncrementId()),
                    null,
                    false,
                    [
                        'order_id' => $orderId,
                        'store_id' => $storeId,
                    ],
                );
            }

            // Update invitation record
            $invitation->setStatus(InvitationInterface::STATUS_COMPLETED);
            $invitation->setReferrerPointsEarned($referrerPoints);
            $invitation->setRefereePointsEarned($refereePoints);
            $this->invitationResource->save($invitation);
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'RewardPoints: SalesOrderCompleteObserver failed (LocalizedException)',
                [
                    'order_id' => $order->getEntityId(),
                    'message' => $e->getMessage(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: SalesOrderCompleteObserver unexpected error',
                [
                    'order_id' => $order->getEntityId(),
                    'exception' => $e,
                ],
            );
        }
    }

    /**
     * Load signed-up invitation for customer
     *
     * @param int $customerId
     * @param int $websiteId
     * @return \Meetanshi\RewardPoints\Model\Invitation|null
     */
    private function loadSignedUpInvitation(int $customerId, int $websiteId): ?\Meetanshi\RewardPoints\Model\Invitation
    {
        $collection = $this->invitationCollectionFactory->create();
        $collection->addFieldToFilter('referee_customer_id', $customerId);
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->addFieldToFilter('status', InvitationInterface::STATUS_SIGNED_UP);
        $collection->setPageSize(1);

        /** @var \Meetanshi\RewardPoints\Model\Invitation $invitation */
        $invitation = $collection->getFirstItem();

        return $invitation->getId() ? $invitation : null;
    }

    /**
     * Check if this is the customer's first completed order
     *
     * @param int $customerId
     * @param int $currentOrderId
     * @return bool
     */
    private function isFirstCompletedOrder(int $customerId, int $currentOrderId): bool
    {
        $connection = $this->invitationResource->getConnection();
        $select = $connection->select()
            ->from('sales_order', ['entity_id'])
            ->where('customer_id = ?', $customerId)
            ->where('status = ?', Order::STATE_COMPLETE)
            ->where('entity_id != ?', $currentOrderId)
            ->limit(1);

        return !(bool) $connection->fetchOne($select);
    }

    /**
     * Get active referral rule applicable to the given website and referrer's customer group.
     *
     * A rule applies when:
     *  - referrer_customer_group_ids is NULL or empty (applies to all groups), OR
     *  - the referrer's customer group ID is in the comma-separated list
     *
     * @param int $websiteId
     * @param int $customerGroupId
     * @return \Magento\Framework\DataObject|null
     */
    private function getActiveReferralRule(
        int $websiteId,
        int $customerGroupId,
    ): ?\Magento\Framework\DataObject {
        $collection = $this->referralRuleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('priority', 'ASC');

        /** @var \Meetanshi\RewardPoints\Model\Rule\ReferralRule $rule */
        foreach ($collection as $rule) {
            $groupIds = $rule->getData('referrer_customer_group_ids');

            if (empty($groupIds)) {
                return $rule;
            }

            $allowed = array_map('intval', array_map('trim', explode(',', (string) $groupIds)));

            if (in_array($customerGroupId, $allowed, true)) {
                return $rule;
            }
        }

        return null;
    }
}
