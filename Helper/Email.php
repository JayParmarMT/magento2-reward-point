<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Psr\Log\LoggerInterface;

/**
 * Email notification helper for reward points events
 */
class Email extends AbstractHelper
{
    // Default template identifiers used when no custom template is saved in admin config.
    // These match the <template id="..."> values registered in email_templates.xml so that
    // the fallback works even before an admin saves the configuration for the first time.
    private const DEFAULT_TEMPLATE_BALANCE_UPDATE = 'meetanshi_rewardpoints_email_update_balance_template';
    private const DEFAULT_TEMPLATE_EXPIRATION_REMINDER = 'meetanshi_rewardpoints_email_expiration_template';
    private const DEFAULT_TEMPLATE_TIER_UPGRADE = 'meetanshi_rewardpoints_email_tier_upgrade_template';
    private const DEFAULT_TEMPLATE_TIER_DOWNGRADE = 'meetanshi_rewardpoints_email_tier_downgrade_template';
    private const DEFAULT_TEMPLATE_BIRTHDAY_BONUS = 'meetanshi_rewardpoints_email_birthday_template';
    private const DEFAULT_TEMPLATE_REFERRAL_INVITATION = 'meetanshi_rewardpoints_referral_invitation_email_template';
    private const DEFAULT_TEMPLATE_REFEREE_WELCOME = 'meetanshi_rewardpoints_referral_api_invitation_email_template';
    private const TEMPLATE_PENDING_APPROVAL = 'meetanshi_rewardpoints_pending_approval';

    /**
     * @param Context $context
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * Send balance update notification
     *
     * @param AccountInterface $account
     * @param TransactionInterface $transaction
     * @param int $storeId
     * @return void
     */
    public function sendBalanceUpdate(
        AccountInterface $account,
        TransactionInterface $transaction,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        if (!$account->isSubscribedBalance()) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $pointsDelta = $transaction->getPointsDelta();
        $actionLabel = $this->getActionLabel($transaction->getActionCode(), $pointsDelta);

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'points_delta' => $pointsDelta,
            'new_balance' => $account->getPointsBalance(),
            'action_label' => $actionLabel,
            'comment' => $transaction->getComment() ?? '',
            'store_name' => $this->getStoreName($storeId),
        ];

        $template = $this->config->getUpdateBalanceTemplate($storeId)
            ?: self::DEFAULT_TEMPLATE_BALANCE_UPDATE;

        $this->sendEmail(
            $template,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Send expiration reminder notification
     *
     * @param AccountInterface $account
     * @param TransactionInterface $transaction
     * @param int $daysUntilExpiry
     * @param int $storeId
     * @return void
     */
    public function sendExpirationReminder(
        AccountInterface $account,
        TransactionInterface $transaction,
        int $daysUntilExpiry,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        if (!$account->isSubscribedExpiration()) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $expiresAt = $transaction->getExpiresAt();
        $expiresDate = $expiresAt ? date('F j, Y', strtotime($expiresAt)) : '';

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'points_expiring' => abs($transaction->getPointsDelta()),
            'expires_in_days' => $daysUntilExpiry,
            'expires_date' => $expiresDate,
            'store_name' => $this->getStoreName($storeId),
        ];

        $template = $this->config->getExpirationTemplate($storeId)
            ?: self::DEFAULT_TEMPLATE_EXPIRATION_REMINDER;

        $this->sendEmail(
            $template,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Send tier change notification (upgrade or downgrade)
     *
     * @param AccountInterface $account
     * @param TierInterface|null $oldTier
     * @param TierInterface $newTier
     * @param bool $isUpgrade
     * @param int $storeId
     * @return void
     */
    public function sendTierChange(
        AccountInterface $account,
        ?TierInterface $oldTier,
        TierInterface $newTier,
        bool $isUpgrade,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $oldTierName = $oldTier ? $oldTier->getName() : __('No Tier')->render();

        $template = $isUpgrade
            ? ($this->config->getTierUpgradeTemplate($storeId) ?: self::DEFAULT_TEMPLATE_TIER_UPGRADE)
            : ($this->config->getTierDowngradeTemplate($storeId) ?: self::DEFAULT_TEMPLATE_TIER_DOWNGRADE);

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'old_tier' => $oldTierName,
            'new_tier' => $newTier->getName(),
            'new_balance' => $account->getPointsBalance(),
            'store_name' => $this->getStoreName($storeId),
        ];

        $this->sendEmail(
            $template,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Send birthday bonus notification
     *
     * @param AccountInterface $account
     * @param int $points
     * @param int $storeId
     * @return void
     */
    public function sendBirthdayBonus(
        AccountInterface $account,
        int $points,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'points_awarded' => $points,
            'new_balance' => $account->getPointsBalance(),
            'store_name' => $this->getStoreName($storeId),
        ];

        $template = $this->config->getBirthdayTemplate($storeId)
            ?: self::DEFAULT_TEMPLATE_BIRTHDAY_BONUS;

        $this->sendEmail(
            $template,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Send referral invitation email
     *
     * @param string $toEmail
     * @param string $referrerName
     * @param string $referralUrl
     * @param string $message
     * @param int $storeId
     * @return void
     */
    public function sendReferralInvitation(
        string $toEmail,
        string $referrerName,
        string $referralUrl,
        string $message,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        $templateVars = [
            'referrer_name' => $referrerName,
            'referral_url' => $referralUrl,
            'invitation_message' => $message,
            'store_name' => $this->getStoreName($storeId),
        ];

        $template = $this->config->getReferralInvitationEmailTemplate($storeId)
            ?: self::DEFAULT_TEMPLATE_REFERRAL_INVITATION;

        $this->sendEmail(
            $template,
            $templateVars,
            $toEmail,
            '',
            $storeId,
        );
    }

    /**
     * Send referee welcome email
     *
     * @param AccountInterface $account
     * @param float $discountAmount
     * @param int $storeId
     * @return void
     */
    public function sendRefereeWelcome(
        AccountInterface $account,
        float $discountAmount,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'referee_discount' => number_format($discountAmount, 2),
            'store_name' => $this->getStoreName($storeId),
        ];

        $template = $this->config->getReferralApiInvitationEmailTemplate($storeId)
            ?: self::DEFAULT_TEMPLATE_REFEREE_WELCOME;

        $this->sendEmail(
            $template,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Send pending approval notification
     *
     * @param AccountInterface $account
     * @param int $pendingPoints
     * @param int $storeId
     * @return void
     */
    public function sendPendingApproval(
        AccountInterface $account,
        int $pendingPoints,
        int $storeId,
    ): void {
        if (!$this->config->isEmailNotificationEnabled($storeId)) {
            return;
        }

        $customerEmail = $this->getCustomerEmail($account);

        if (empty($customerEmail)) {
            return;
        }

        $templateVars = [
            'customer' => ['name' => $this->getCustomerName($account)],
            'points_pending' => $pendingPoints,
            'store_name' => $this->getStoreName($storeId),
        ];

        $this->sendEmail(
            self::TEMPLATE_PENDING_APPROVAL,
            $templateVars,
            $customerEmail,
            $this->getCustomerName($account),
            $storeId,
        );
    }

    /**
     * Core email sending method
     *
     * @param string $templateId
     * @param array<string, mixed> $templateVars
     * @param string $toEmail
     * @param string $toName
     * @param int $storeId
     * @return void
     * @throws LocalizedException
     */
    private function sendEmail(
        string $templateId,
        array $templateVars,
        string $toEmail,
        string $toName,
        int $storeId,
    ): void {
        try {
            $sender = $this->config->getEmailSender($storeId);

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope($sender, $storeId)
                ->addTo($toEmail, $toName)
                ->getTransport();

            $transport->sendMessage();
        } catch (MailException $e) {
            $this->logger->error(
                'Meetanshi RewardPoints: Failed to send email notification',
                [
                    'template' => $templateId,
                    'to' => $toEmail,
                    'exception' => $e->getMessage(),
                ],
            );
        }
    }

    /**
     * Get customer email from account data
     *
     * @param AccountInterface $account
     * @return string
     */
    private function getCustomerEmail(AccountInterface $account): string
    {
        if ($account instanceof \Magento\Framework\DataObject) {
            return (string) $account->getData('customer_email');
        }

        return '';
    }

    /**
     * Get customer display name from account data
     *
     * @param AccountInterface $account
     * @return string
     */
    private function getCustomerName(AccountInterface $account): string
    {
        if ($account instanceof \Magento\Framework\DataObject) {
            $firstName = (string) $account->getData('customer_firstname');
            $lastName = (string) $account->getData('customer_lastname');
            $fullName = trim($firstName . ' ' . $lastName);

            return $fullName !== '' ? $fullName : (string) $account->getData('customer_email');
        }

        return '';
    }

    /**
     * Get store name by store ID
     *
     * @param int $storeId
     * @return string
     */
    private function getStoreName(int $storeId): string
    {
        try {
            return $this->storeManager->getStore($storeId)->getName();
        } catch (NoSuchEntityException $e) {
            return '';
        }
    }

    /**
     * Get human-readable action label from action code and delta
     *
     * @param string $actionCode
     * @param int $pointsDelta
     * @return string
     */
    private function getActionLabel(string $actionCode, int $pointsDelta): string
    {
        $labels = [
            'order_earn' => __('Order Purchase')->render(),
            'order_cancel' => __('Order Cancelled')->render(),
            'order_refund' => __('Order Refunded')->render(),
            'admin_add' => __('Admin Adjustment (Add)')->render(),
            'admin_deduct' => __('Admin Adjustment (Deduct)')->render(),
            'spend' => __('Points Redeemed')->render(),
            'expire' => __('Points Expired')->render(),
            'birthday' => __('Birthday Bonus')->render(),
            'referral_referrer' => __('Referral Reward')->render(),
            'referral_referee' => __('Referral Welcome')->render(),
        ];

        if (isset($labels[$actionCode])) {
            return $labels[$actionCode];
        }

        return $pointsDelta >= 0
            ? __('Points Earned')->render()
            : __('Points Deducted')->render();
    }
}
