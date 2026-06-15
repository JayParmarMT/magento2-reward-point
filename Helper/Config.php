<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration Helper
 */
class Config extends AbstractHelper
{
    // ── General ──────────────────────────────────────────────────────────────
    private const XML_PATH_ENABLED = 'meetanshi_rewardpoints/general/enabled';
    private const XML_PATH_LABEL = 'meetanshi_rewardpoints/general/label';
    private const XML_PATH_POINT_LABEL = 'meetanshi_rewardpoints/general/point_label';
    private const XML_PATH_POINT_LABEL_PLURAL = 'meetanshi_rewardpoints/general/point_label_plural';
    private const XML_PATH_POINT_LABEL_POSITION = 'meetanshi_rewardpoints/general/label_position';
    private const XML_PATH_ZERO_POINT_LABEL = 'meetanshi_rewardpoints/general/zero_point_label';
    private const XML_PATH_SHOW_POINT_ICON = 'meetanshi_rewardpoints/general/show_icon';
    private const XML_PATH_POINT_ICON = 'meetanshi_rewardpoints/general/point_icon';
    private const XML_PATH_MAX_BALANCE = 'meetanshi_rewardpoints/general/max_balance';
    private const XML_PATH_REDIRECT_AFTER_LOGIN = 'meetanshi_rewardpoints/general/redirect_after_login';

    // ── Landing Page ─────────────────────────────────────────────────────────
    private const XML_PATH_LANDING_PAGE_ID = 'meetanshi_rewardpoints/landing_page/page_id';
    private const XML_PATH_LANDING_PAGE_SHOW_FOOTER_LINK = 'meetanshi_rewardpoints/landing_page/show_footer_link';
    private const XML_PATH_LANDING_PAGE_FOOTER_LABEL = 'meetanshi_rewardpoints/landing_page/footer_label';

    // ── Highlight ────────────────────────────────────────────────────────────
    // NOTE: these paths intentionally use the "highlight" group (system.xml group id="highlight")
    private const XML_PATH_HIGHLIGHT_SHOW_IN_CART = 'meetanshi_rewardpoints/highlight/show_in_cart';
    private const XML_PATH_HIGHLIGHT_SHOW_ON_CHECKOUT = 'meetanshi_rewardpoints/highlight/show_on_checkout';
    private const XML_PATH_HIGHLIGHT_SHOW_ON_PRODUCT = 'meetanshi_rewardpoints/highlight/show_on_product';
    private const XML_PATH_HIGHLIGHT_SHOW_ON_CATEGORY = 'meetanshi_rewardpoints/highlight/show_on_category';
    private const XML_PATH_SHOW_FOR_GUESTS = 'meetanshi_rewardpoints/highlight/show_for_guests';
    private const XML_PATH_HIGHLIGHT_TEXT_COLOR = 'meetanshi_rewardpoints/highlight/highlight_text_color';

    // ── Earning ───────────────────────────────────────────────────────────────
    private const XML_PATH_ROUNDING_METHOD = 'meetanshi_rewardpoints/earning/rounding_method';
    private const XML_PATH_EARN_FROM_TAX = 'meetanshi_rewardpoints/earning/earn_from_tax';
    private const XML_PATH_EARN_FROM_SHIPPING = 'meetanshi_rewardpoints/earning/earn_from_shipping';
    private const XML_PATH_POINT_REFUND = 'meetanshi_rewardpoints/earning/point_refund';
    private const XML_PATH_HOLDING_DAYS = 'meetanshi_rewardpoints/earning/point_holding_days';
    private const XML_PATH_EARN_FROM_COUPON = 'meetanshi_rewardpoints/earning/earn_from_coupon_orders';
    private const XML_PATH_CALCULATION_TYPE = 'meetanshi_rewardpoints/earning/calculation_type';
    private const XML_PATH_EARN_AFTER_INVOICE = 'meetanshi_rewardpoints/earning/earn_after_invoice';
    private const XML_PATH_POINTS_EXPIRE_DAYS = 'meetanshi_rewardpoints/earning/points_expire_after';

    // ── Spending ──────────────────────────────────────────────────────────────
    private const XML_PATH_MIN_SPEND = 'meetanshi_rewardpoints/spending/min_points_per_order';
    private const XML_PATH_DISCOUNT_LABEL = 'meetanshi_rewardpoints/spending/discount_label';
    private const XML_PATH_MAX_SPEND_TYPE = 'meetanshi_rewardpoints/spending/max_points_type';
    private const XML_PATH_MAX_SPEND = 'meetanshi_rewardpoints/spending/max_points_per_order';
    private const XML_PATH_SPEND_ON_SHIPPING = 'meetanshi_rewardpoints/spending/spend_on_shipping';
    private const XML_PATH_RESTORE_SPENT = 'meetanshi_rewardpoints/spending/restore_spent_on_refund';
    private const XML_PATH_USE_MAX_DEFAULT = 'meetanshi_rewardpoints/spending/use_max_by_default';
    private const XML_PATH_SPEND_FROM_COUPON = 'meetanshi_rewardpoints/spending/spend_from_coupon_orders';
    private const XML_PATH_APPLY_AFTER_TAX = 'meetanshi_rewardpoints/spending/apply_after_tax';
    private const XML_PATH_INCLUDE_TAX = 'meetanshi_rewardpoints/spending/include_tax';

    // ── Social Sharing ────────────────────────────────────────────────────────
    private const XML_PATH_SOCIAL_PAGES           = 'meetanshi_rewardpoints/social/social_pages';
    private const XML_PATH_SOCIAL_FACEBOOK_SHOW   = 'meetanshi_rewardpoints/social/facebook_show_button';
    private const XML_PATH_SOCIAL_TWITTER_SHOW    = 'meetanshi_rewardpoints/social/twitter_show_button';
    private const XML_PATH_SOCIAL_PINTEREST_SHOW  = 'meetanshi_rewardpoints/social/pinterest_show_button';
    private const XML_PATH_SOCIAL_FACEBOOK_APP_ID = 'meetanshi_rewardpoints/social/facebook_app_id';
    private const XML_PATH_SOCIAL_FACEBOOK_SHOW_COUNT = 'meetanshi_rewardpoints/social/facebook_show_count';
    private const XML_PATH_SOCIAL_MIN_SECONDS = 'meetanshi_rewardpoints/social/min_seconds_between_actions';

    // ── Display ───────────────────────────────────────────────────────────────
    private const XML_PATH_SHOW_TOP_LINKS = 'meetanshi_rewardpoints/display/show_top_links';
    private const XML_PATH_HIDE_IF_ZERO = 'meetanshi_rewardpoints/display/hide_if_zero';
    private const XML_PATH_SHOW_ON_MINICART = 'meetanshi_rewardpoints/display/show_on_minicart';
    private const XML_PATH_SHOW_ON_CART = 'meetanshi_rewardpoints/display/show_on_cart';
    private const XML_PATH_SHOW_AS_CURRENCY = 'meetanshi_rewardpoints/display/show_as_currency';
    private const XML_PATH_SHOW_MAX_FOR_CONFIGURABLE = 'meetanshi_rewardpoints/display/show_max_for_configurable';

    // ── Email Notifications ───────────────────────────────────────────────────
    private const XML_PATH_ENABLE_EMAIL = 'meetanshi_rewardpoints/email/enable_notification';
    private const XML_PATH_EMAIL_SUBSCRIBE_BY_DEFAULT = 'meetanshi_rewardpoints/email/subscribe_by_default';
    private const XML_PATH_EMAIL_SENDER = 'meetanshi_rewardpoints/email/sender';
    private const XML_PATH_EMAIL_UPDATE_BALANCE_TEMPLATE = 'meetanshi_rewardpoints/email/update_balance_template';
    private const XML_PATH_EMAIL_EXPIRATION_TEMPLATE = 'meetanshi_rewardpoints/email/expiration_template';
    private const XML_PATH_EMAIL_TIER_UPGRADE_TEMPLATE = 'meetanshi_rewardpoints/email/tier_upgrade_template';
    private const XML_PATH_EMAIL_TIER_DOWNGRADE_TEMPLATE = 'meetanshi_rewardpoints/email/tier_downgrade_template';
    private const XML_PATH_EMAIL_BIRTHDAY_TEMPLATE = 'meetanshi_rewardpoints/email/birthday_template';
    private const XML_PATH_EXPIRE_REMINDER_DAYS = 'meetanshi_rewardpoints/email/expire_reminder_days';

    // ── Referral ──────────────────────────────────────────────────────────────
    private const XML_PATH_REFERRAL_INVITATION_EMAIL_TEMPLATE = 'meetanshi_rewardpoints/referral/invitation_email_template';
    private const XML_PATH_REFERRAL_API_INVITATION_EMAIL_TEMPLATE = 'meetanshi_rewardpoints/referral/api_invitation_email_template';
    private const XML_PATH_REFERRAL_URL_KEY_MODE = 'meetanshi_rewardpoints/referral/url_key_mode';
    private const XML_PATH_REFERRAL_CODE_PREFIX = 'meetanshi_rewardpoints/referral/code_prefix';
    private const XML_PATH_REFERRAL_DEFAULT_REFER_URL = 'meetanshi_rewardpoints/referral/default_refer_url';
    private const XML_PATH_REFERRAL_INVITATION_MESSAGE = 'meetanshi_rewardpoints/referral/invitation_message';
    private const XML_PATH_REFERRAL_ADDTOANY_CODE = 'meetanshi_rewardpoints/referral/addtoany_code';

    // ── Tier / Milestone ──────────────────────────────────────────────────────
    private const XML_PATH_TIER_ENABLED = 'meetanshi_rewardpoints/tier/enabled';
    private const XML_PATH_TIER_BASIS = 'meetanshi_rewardpoints/tier/basis';
    private const XML_PATH_TIER_PERIOD = 'meetanshi_rewardpoints/tier/period';
    private const XML_PATH_TIER_AUTO_DEMOTE = 'meetanshi_rewardpoints/tier/auto_demote';
    private const XML_PATH_TIER_PROGRESS_BG_COLOR = 'meetanshi_rewardpoints/tier/progress_bg_color';
    private const XML_PATH_TIER_PROGRESS_COLOR = 'meetanshi_rewardpoints/tier/progress_color';
    private const XML_PATH_TIER_EMAIL_ON_CHANGE = 'meetanshi_rewardpoints/tier/email_on_tier_change';

    // ── Advanced ──────────────────────────────────────────────────────────────
    private const XML_PATH_ADVANCED_ROUND_DOWN = 'meetanshi_rewardpoints/advanced/round_down';
    private const XML_PATH_ADVANCED_CUSTOM_EVENTS = 'meetanshi_rewardpoints/advanced/custom_behavior_events';
    private const XML_PATH_ADVANCED_FORCE_STYLES = 'meetanshi_rewardpoints/advanced/force_apply_styles';

    // ── Legacy alias kept for back-compat ─────────────────────────────────────
    private const XML_PATH_HIGHLIGHT_ENABLED = 'meetanshi_rewardpoints/highlight/show_on_product';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get My Account navigation label
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get singular point label
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPointLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_POINT_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get plural point label
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPointLabelPlural(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_POINT_LABEL_PLURAL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get zero point label
     *
     * @param int|null $storeId
     * @return string
     */
    public function getZeroPointLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ZERO_POINT_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get max balance (0 = unlimited)
     *
     * @param int|null $scopeId
     * @param string $scope
     * @return int
     */
    public function getMaxBalance(?int $scopeId = null, string $scope = ScopeInterface::SCOPE_WEBSITE): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_BALANCE,
            $scope,
            $scopeId,
        );
    }

    /**
     * Get rounding method
     *
     * @param int|null $storeId
     * @return string normal|up|down
     */
    public function getRoundingMethod(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ROUNDING_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Earn points from tax?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEarnFromTax(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EARN_FROM_TAX,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Earn points from shipping?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEarnFromShipping(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EARN_FROM_SHIPPING,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Cancel points on refund?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPointRefundEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_POINT_REFUND,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get holding period in days (0 = no hold)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getHoldingDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_HOLDING_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Earn from coupon orders?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEarnFromCouponOrders(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EARN_FROM_COUPON,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Earn after invoice created (vs. after order complete)?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEarnAfterInvoice(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EARN_AFTER_INVOICE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get points expiration days (0 = never expire)
     *
     * @param int|null $scopeId
     * @param string $scope
     * @return int
     */
    public function getPointsExpireDays(
        ?int $scopeId = null,
        string $scope = ScopeInterface::SCOPE_WEBSITE,
    ): int {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_POINTS_EXPIRE_DAYS,
            $scope,
            $scopeId,
        );
    }

    /**
     * Get minimum spending points per order
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMinSpendingPoints(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_SPEND,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get discount label
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDiscountLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_DISCOUNT_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get maximum spending points per order
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxSpendingPoints(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_SPEND,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Can spend on shipping?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSpendOnShipping(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SPEND_ON_SHIPPING,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Restore spent points on refund?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRestoreSpentOnRefund(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTORE_SPENT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Use max points by default?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUseMaxByDefault(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_MAX_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Is email notification enabled?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEmailNotificationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get email sender identity
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEmailSender(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_SENDER,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get expiration reminder days (comma-separated string → int[])
     *
     * @param int|null $storeId
     * @return int[]
     */
    public function getExpireReminderDays(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EXPIRE_REMINDER_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );

        if (empty($value)) {
            return [];
        }

        $days = [];

        foreach (explode(',', $value) as $d) {
            $day = (int) trim($d);

            if ($day > 0) {
                $days[] = $day;
            }
        }

        return array_unique($days);
    }

    /**
     * Get maximum spending points type (fixed|percent)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMaxSpendType(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_SPEND_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Is earn-message highlight enabled on product/cart?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isHighlightEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIGHLIGHT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get highlight text color (hex string)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getHighlightTextColor(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_HIGHLIGHT_TEXT_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show reward-points balance/earn messages to guests?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowForGuests(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_FOR_GUESTS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get referral invitation email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralInvitationEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_INVITATION_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show reward points balance in top links?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowTopLinks(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_TOP_LINKS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Hide balance when zero?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isHideIfZero(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIDE_IF_ZERO,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show balance in minicart?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowOnMinicart(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_ON_MINICART,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show reward points redemption block on cart page?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowOnCart(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_ON_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show points balance as currency equivalent?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowAsCurrency(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_AS_CURRENCY,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Is tier program enabled?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isTierEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TIER_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // General — additional getters
    // =========================================================================

    /**
     * Get point label position (before|after)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPointLabelPosition(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_POINT_LABEL_POSITION,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show point icon next to balance?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowPointIcon(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_POINT_ICON,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get uploaded point icon path
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPointIcon(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_POINT_ICON,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Redirect to My Reward Points page after login?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRedirectAfterLogin(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REDIRECT_AFTER_LOGIN,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Landing Page
    // =========================================================================

    /**
     * Get the CMS page ID for the Reward Points landing page
     *
     * @param int|null $storeId
     * @return int
     */
    public function getLandingPageId(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_LANDING_PAGE_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show reward points link in the footer?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowFooterLink(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LANDING_PAGE_SHOW_FOOTER_LINK,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get footer link label text
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFooterLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LANDING_PAGE_FOOTER_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Highlight — per-placement visibility flags
    // =========================================================================

    /**
     * Show earn-points message in cart?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowInCart(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIGHLIGHT_SHOW_IN_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show earn-points message on checkout?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowOnCheckout(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIGHLIGHT_SHOW_ON_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show earn-points message on product pages?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowOnProduct(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIGHLIGHT_SHOW_ON_PRODUCT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show earn-points message on category listing pages?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowOnCategory(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIGHLIGHT_SHOW_ON_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Earning — additional getters
    // =========================================================================

    /**
     * Get calculation type (before_tax|after_tax)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCalculationType(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_CALCULATION_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Spending — additional getters
    // =========================================================================

    /**
     * Allow spending points on orders that used a coupon?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSpendFromCouponOrders(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SPEND_FROM_COUPON,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Apply reward discount after tax?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isApplyAfterTax(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_APPLY_AFTER_TAX,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Include tax in the discountable amount?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isIncludeTax(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_TAX,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Display — additional getters
    // =========================================================================

    /**
     * Show max possible earn points for configurable products?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShowMaxForConfigurable(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_MAX_FOR_CONFIGURABLE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Email — template getters
    // =========================================================================

    /**
     * Subscribe customers to emails by default?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSubscribeByDefault(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EMAIL_SUBSCRIBE_BY_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get balance update email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getUpdateBalanceTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_UPDATE_BALANCE_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get expiration reminder email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getExpirationTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_EXPIRATION_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get tier upgrade email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTierUpgradeTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TIER_UPGRADE_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get tier downgrade email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTierDowngradeTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TIER_DOWNGRADE_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get birthday bonus email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getBirthdayTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_BIRTHDAY_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Referral — additional getters
    // =========================================================================

    /**
     * Get referral API invitation email template identifier
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralApiInvitationEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_API_INVITATION_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get referral URL key mode (param|subdomain|path)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralUrlKeyMode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_URL_KEY_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get referral code prefix
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralCodePrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_CODE_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get default refer URL for sharing
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralDefaultReferUrl(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_DEFAULT_REFER_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get default invitation message text
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralInvitationMessage(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_INVITATION_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get AddToAny embed code for the referral page
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAddToAnyCode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_ADDTOANY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Tier / Milestone — additional getters
    // =========================================================================

    /**
     * Get tier basis (earned_points|spent_points|order_count)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTierBasis(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_BASIS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get tier recalculation period in days (0 = all-time)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getTierPeriod(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_PERIOD,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Auto-demote customer tier when points drop below threshold?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAutoDemote(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TIER_AUTO_DEMOTE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get tier progress bar background color (hex)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTierProgressBgColor(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_PROGRESS_BG_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get tier progress bar fill color (hex)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTierProgressColor(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_TIER_PROGRESS_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Send email notification on tier change?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEmailOnTierChange(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TIER_EMAIL_ON_CHANGE,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Advanced
    // =========================================================================

    /**
     * Always round points down (override rounding_method)?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRoundDown(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ADVANCED_ROUND_DOWN,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get custom behavior event definitions (one "code,Label" per line)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCustomBehaviorEvents(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ADVANCED_CUSTOM_EVENTS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Force apply inline styles to reward point elements?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isForceApplyStyles(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ADVANCED_FORCE_STYLES,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Social Sharing
    // =========================================================================

    /**
     * Get pages where social sharing buttons should appear.
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getSocialPages(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_PAGES,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );

        // Default: all pages enabled when not yet configured
        if (empty($value)) {
            return ['referral', 'account', 'product'];
        }

        return array_filter(array_map('trim', explode(',', $value)));
    }

    /**
     * Show Facebook share button?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSocialFacebookEnabled(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_FACEBOOK_SHOW,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
        // Default true when not yet saved in DB
        return $value === null ? true : (bool) $value;
    }

    /**
     * Show Twitter share button?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSocialTwitterEnabled(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_TWITTER_SHOW,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
        return $value === null ? true : (bool) $value;
    }

    /**
     * Show Pinterest share button?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSocialPinterestEnabled(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_PINTEREST_SHOW,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
        return $value === null ? true : (bool) $value;
    }

    /**
     * Get Facebook App ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFacebookAppId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_FACEBOOK_APP_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Show Facebook share count alongside share button?
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSocialFacebookShowCount(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SOCIAL_FACEBOOK_SHOW_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    /**
     * Get minimum seconds between social sharing actions (0 = no limit)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMinSecondsBetweenSocialActions(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_SOCIAL_MIN_SECONDS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
    }

    // =========================================================================
    // Utility
    // =========================================================================

    /**
     * Format points with label
     *
     * @param int $points
     * @param int|null $storeId
     * @return string
     */
    public function formatPoints(int $points, ?int $storeId = null): string
    {
        if ($points === 0) {
            return $this->getZeroPointLabel($storeId);
        }

        $label = $points === 1
            ? $this->getPointLabel($storeId)
            : $this->getPointLabelPlural($storeId);

        return $points . ' ' . $label;
    }
}
