<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Helper\Email as EmailHelper;
use Meetanshi\RewardPoints\Model\InvitationFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation as InvitationResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation\CollectionFactory as InvitationCollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode\CollectionFactory as ReferralCodeCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\BehaviorRuleConditionValidator;
use Meetanshi\RewardPoints\Model\Source\BehaviorEvent;
use Meetanshi\RewardPoints\Model\TierCalculator;
use Psr\Log\LoggerInterface;

/**
 * Observer for customer registration success — processes referral codes and awards signup behavior points
 */
class CustomerRegisterSuccessObserver implements ObserverInterface
{
    private const SESSION_KEY_REFERRAL_CODE = 'meetanshi_referral_code';
    private const COOKIE_NAME_REFERRAL_CODE = 'meetanshi_referral_code';

    /**
     * @param CustomerSession $customerSession
     * @param HttpRequest $request
     * @param StoreManagerInterface $storeManager
     * @param ReferralCodeCollectionFactory $referralCodeCollectionFactory
     * @param InvitationCollectionFactory $invitationCollectionFactory
     * @param InvitationFactory $invitationFactory
     * @param InvitationResource $invitationResource
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountRepositoryInterface $accountRepository
     * @param BalanceManagementInterface $balanceManagement
     * @param ResourceConnection $resourceConnection
     * @param TimezoneInterface $timezone
     * @param Config $config
     * @param TierCalculator $tierCalculator
     * @param EmailHelper $emailHelper
     * @param BehaviorRuleConditionValidator $behaviorRuleConditionValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReferralCodeCollectionFactory $referralCodeCollectionFactory,
        private readonly InvitationCollectionFactory $invitationCollectionFactory,
        private readonly InvitationFactory $invitationFactory,
        private readonly InvitationResource $invitationResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly ResourceConnection $resourceConnection,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        private readonly TierCalculator $tierCalculator,
        private readonly EmailHelper $emailHelper,
        private readonly BehaviorRuleConditionValidator $behaviorRuleConditionValidator,
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

        try {
            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getData('customer');

            if (!$customer || !$customer->getId()) {
                return;
            }

            $refereeCustomerId = (int) $customer->getId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $storeId = (int) $this->storeManager->getStore()->getId();
            $todayDate = $this->timezone->date()->format('Y-m-d');
            $customerGroupId = (int) $customer->getGroupId();

            // Award signup behavior rule points (event_code = 'signup')
            $this->awardBehaviorRulePoints(
                $refereeCustomerId,
                $websiteId,
                BehaviorEvent::EVENT_SIGNUP,
                $todayDate,
                $customerGroupId,
            );

            // D-06: assign initial tier immediately after registration when tier is enabled.
            // The nightly TierRecalculate cron keeps tiers current; this ensures the customer
            // has a tier from the moment they register rather than waiting until the next cron run.
            $this->assignInitialTier($refereeCustomerId, $websiteId, $storeId, $customerGroupId);

            // Process referral code
            $referralCode = $this->resolveReferralCode();

            if (empty($referralCode)) {
                return;
            }

            $referralCodeModel = $this->loadReferralCodeModel($referralCode, $websiteId);

            if (!$referralCodeModel) {
                return;
            }

            $referrerCustomerId = (int) $referralCodeModel->getData('customer_id');

            // Prevent self-referral
            if ($referrerCustomerId === $refereeCustomerId) {
                return;
            }

            // Check if referee already has an invitation (prevent duplicate)
            if ($this->hasExistingInvitation($refereeCustomerId, $websiteId)) {
                return;
            }

            $invitation = $this->invitationFactory->create();
            $invitation->setReferrerCustomerId($referrerCustomerId);
            $invitation->setRefereeCustomerId($refereeCustomerId);
            $invitation->setWebsiteId($websiteId);
            $invitation->setRefereeEmail((string) $customer->getEmail());
            $invitation->setReferralCode($referralCode);
            $invitation->setStatus(InvitationInterface::STATUS_SIGNED_UP);
            $invitation->setReferrerPointsEarned(0);
            $invitation->setRefereePointsEarned(0);
            $invitation->setRefereeDiscountEarned(0.0);

            $this->invitationResource->save($invitation);

            // Award refer_signup behavior rule points to the referee
            $this->awardBehaviorRulePoints(
                $refereeCustomerId,
                $websiteId,
                BehaviorEvent::EVENT_REFER_SIGNUP,
                $todayDate,
                $customerGroupId,
            );

            // Send referee welcome email
            try {
                $account = $this->accountRepository->getOrCreate($refereeCustomerId, $websiteId);
                $account->setData('customer_email', $customer->getEmail());
                $account->setData('customer_firstname', $customer->getFirstname());
                $account->setData('customer_lastname', $customer->getLastname());
                $this->emailHelper->sendRefereeWelcome($account, 0.0, $storeId);
            } catch (\Exception $e) {
                $this->logger->warning(
                    'RewardPoints: CustomerRegisterSuccessObserver referee welcome email failed',
                    ['message' => $e->getMessage()],
                );
            }

            // Clear session referral code after use
            $this->customerSession->unsetData(self::SESSION_KEY_REFERRAL_CODE);
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'RewardPoints: CustomerRegisterSuccessObserver failed (LocalizedException)',
                ['message' => $e->getMessage()],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: CustomerRegisterSuccessObserver unexpected error',
                ['exception' => $e],
            );
        }
    }

    /**
     * Award behavior rule points for a given event code to a customer.
     *
     * Finds active behavior rules for the event, checks website/customer group scope
     * via junction tables, checks caps in the behavior_log, awards points via
     * BalanceManagement, and updates the log.
     *
     * @param int $customerId
     * @param int $websiteId
     * @param string $eventCode
     * @param string $todayDate
     * @param int $customerGroupId
     * @return void
     */
    private function awardBehaviorRulePoints(
        int $customerId,
        int $websiteId,
        string $eventCode,
        string $todayDate,
        int $customerGroupId = 0,
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $ruleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_rule');
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');

        $ruleSelect = $connection->select()
            ->from($ruleTable)
            ->where('is_active = ?', 1)
            ->where('event_code = ?', $eventCode)
            ->where('from_date IS NULL OR from_date <= ?', $todayDate)
            ->where('to_date IS NULL OR to_date >= ?', $todayDate)
            ->order('priority ASC');

        $rules = $connection->fetchAll($ruleSelect);

        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            $ruleId = (int) $rule['rule_id'];
            $points = (int) $rule['points'];

            if ($points <= 0) {
                continue;
            }

            // Filter by website and customer group via junction tables
            if (!$this->behaviorRuleMatchesScope($ruleId, $websiteId, $customerGroupId)) {
                continue;
            }

            // Validate conditions_serialized against the customer before awarding
            if (!$this->behaviorRuleConditionValidator->ruleMatchesCustomer(
                $ruleId,
                $rule['conditions_serialized'] ?? null,
                $customerId,
            )) {
                continue;
            }

            // Check cap: signup/refer_signup are one-time events (cap_lifetime = 1 by convention)
            $capLifetime = (int) ($rule['cap_lifetime'] ?? 0);

            if ($capLifetime > 0) {
                $logRow = $connection->fetchRow(
                    $connection->select()
                        ->from($logTable, ['points_earned_lifetime'])
                        ->where('customer_id = ?', $customerId)
                        ->where('rule_id = ?', $ruleId),
                );

                if ($logRow && (int) $logRow['points_earned_lifetime'] >= $capLifetime) {
                    continue;
                }
            }

            try {
                $this->balanceManagement->addPoints(
                    $customerId,
                    $websiteId,
                    $points,
                    $eventCode,
                    (string) __('Reward for %1', $eventCode),
                );

                $this->updateBehaviorLog($customerId, $ruleId, $eventCode, $points, $todayDate);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'RewardPoints: behavior rule award failed for customer %d, rule %d: %s',
                        $customerId,
                        $ruleId,
                        $e->getMessage(),
                    ),
                );
            }

            if (!empty($rule['stop_rules_processing'])) {
                break;
            }
        }
    }

    /**
     * Check whether a behavior rule applies to the given website and customer group.
     *
     * Empty junction rows mean "applies to all".
     *
     * @param int $ruleId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return bool
     */
    private function behaviorRuleMatchesScope(int $ruleId, int $websiteId, int $customerGroupId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $websiteRows = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'behavior_earning'),
        );

        if (!empty($websiteRows) && !in_array($websiteId, array_map('intval', $websiteRows), true)) {
            return false;
        }

        $groupRows = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['customer_group_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'behavior_earning'),
        );

        if (!empty($groupRows) && !in_array($customerGroupId, array_map('intval', $groupRows), true)) {
            return false;
        }

        return true;
    }

    /**
     * Update or insert behavior log entry to track cap counters
     *
     * @param int $customerId
     * @param int $ruleId
     * @param string $eventCode
     * @param int $points
     * @param string $todayDate
     * @return void
     */
    private function updateBehaviorLog(
        int $customerId,
        int $ruleId,
        string $eventCode,
        int $points,
        string $todayDate,
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $logTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_behavior_log');

        $existing = $connection->fetchRow(
            $connection->select()
                ->from($logTable)
                ->where('customer_id = ?', $customerId)
                ->where('rule_id = ?', $ruleId),
        );

        if ($existing) {
            $connection->update(
                $logTable,
                [
                    'points_earned_today'      => $points,
                    'points_earned_this_month' => (int) $existing['points_earned_this_month'] + $points,
                    'points_earned_this_year'  => (int) $existing['points_earned_this_year'] + $points,
                    'points_earned_lifetime'   => (int) $existing['points_earned_lifetime'] + $points,
                    'last_earned_date'         => $todayDate,
                ],
                ['log_id = ?' => (int) $existing['log_id']],
            );
        } else {
            $connection->insert($logTable, [
                'customer_id'              => $customerId,
                'rule_id'                  => $ruleId,
                'event_code'               => $eventCode,
                'points_earned_today'      => $points,
                'points_earned_this_month' => $points,
                'points_earned_this_year'  => $points,
                'points_earned_lifetime'   => $points,
                'last_earned_date'         => $todayDate,
            ]);
        }
    }

    /**
     * Resolve referral code: POST form field takes priority, then session, then cookie.
     *
     * @return string
     */
    private function resolveReferralCode(): string
    {
        $code = trim((string) $this->request->getParam('meetanshi_referral_code', ''));

        if (!empty($code)) {
            return $code;
        }

        $code = trim((string) $this->customerSession->getData(self::SESSION_KEY_REFERRAL_CODE));

        if (!empty($code)) {
            return $code;
        }

        return trim((string) $this->request->getCookie(self::COOKIE_NAME_REFERRAL_CODE, ''));
    }

    /**
     * Load referral code model by code string and website
     *
     * @param string $code
     * @param int $websiteId
     * @return \Magento\Framework\DataObject|null
     */
    private function loadReferralCodeModel(string $code, int $websiteId): ?\Magento\Framework\DataObject
    {
        $collection = $this->referralCodeCollectionFactory->create();
        $collection->addFieldToFilter('code', $code);
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * Check if customer already has an active invitation as referee
     *
     * @param int $refereeCustomerId
     * @param int $websiteId
     * @return bool
     */
    private function hasExistingInvitation(int $refereeCustomerId, int $websiteId): bool
    {
        $collection = $this->invitationCollectionFactory->create();
        $collection->addFieldToFilter('referee_customer_id', $refereeCustomerId);
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->addFieldToFilter(
            'status',
            ['in' => [InvitationInterface::STATUS_SIGNED_UP, InvitationInterface::STATUS_COMPLETED]],
        );
        $collection->setPageSize(1);

        return $collection->getSize() > 0;
    }

    /**
     * Assign the correct starting tier to a newly registered customer.
     *
     * Most new customers will have zero points and qualify for no tier.
     * When a store has a zero-threshold "entry" tier, this ensures it is
     * immediately visible in the account rather than waiting for the nightly cron.
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $storeId
     * @param int $customerGroupId
     * @return void
     */
    private function assignInitialTier(int $customerId, int $websiteId, int $storeId, int $customerGroupId = 0): void
    {
        if (!$this->config->isTierEnabled($storeId)) {
            return;
        }

        try {
            $account = $this->accountRepository->getOrCreate($customerId, $websiteId);
            $tier    = $this->tierCalculator->getEligibleTier($customerId, $websiteId, $customerGroupId);
            $tierId  = $tier ? (int) $tier->getTierId() : null;

            if ($account->getCurrentTierId() !== $tierId) {
                $account->setCurrentTierId($tierId);
                $this->accountRepository->save($account);
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: CustomerRegisterSuccessObserver failed to assign initial tier',
                ['customer_id' => $customerId, 'message' => $e->getMessage()],
            );
        }
    }
}
