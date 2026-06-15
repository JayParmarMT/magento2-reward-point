<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Behavior event source model
 */
class BehaviorEvent implements OptionSourceInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public const EVENT_SIGNUP = 'signup';
    public const EVENT_FIRST_ORDER = 'first_order';
    public const EVENT_PLACE_ORDER = 'place_order';
    public const EVENT_NEWSLETTER = 'newsletter';
    public const EVENT_REVIEW = 'review';
    public const EVENT_BIRTHDAY = 'birthday';
    public const EVENT_FACEBOOK_LIKE = 'facebook_like';
    public const EVENT_TWITTER_TWEET = 'twitter_tweet';
    public const EVENT_PINTEREST_PIN = 'pinterest_pin';
    public const EVENT_SHARE_PURCHASE_FACEBOOK = 'share_purchase_facebook';
    public const EVENT_SHARE_PURCHASE_TWITTER = 'share_purchase_twitter';
    public const EVENT_SHARE_PURCHASE_PINTEREST = 'share_purchase_pinterest';
    public const EVENT_EMAIL_FRIEND = 'email_friend';
    public const EVENT_INACTIVITY = 'inactivity';
    public const EVENT_PUSH_NOTIFICATION = 'push_notification';
    public const EVENT_AFFILIATE_JOIN = 'affiliate_join';
    public const EVENT_TIER_UP = 'tier_up';
    public const EVENT_TIER_DOWN = 'tier_down';
    public const EVENT_RMA_CREATED = 'rma_created';
    public const EVENT_LIFETIME_AMOUNT = 'lifetime_amount';
    public const EVENT_POINTS_ALLOCATION = 'points_allocation';
    public const EVENT_REFER_SIGNUP = 'refer_signup';
    public const EVENT_REFER_ORDER = 'refer_order';

    /**
     * Get all valid event codes
     *
     * @return string[]
     */
    public static function getValidEventCodes(): array
    {
        return [
            self::EVENT_SIGNUP,
            self::EVENT_FIRST_ORDER,
            self::EVENT_PLACE_ORDER,
            self::EVENT_NEWSLETTER,
            self::EVENT_REVIEW,
            self::EVENT_BIRTHDAY,
            self::EVENT_FACEBOOK_LIKE,
            self::EVENT_TWITTER_TWEET,
            self::EVENT_PINTEREST_PIN,
            self::EVENT_SHARE_PURCHASE_FACEBOOK,
            self::EVENT_SHARE_PURCHASE_TWITTER,
            self::EVENT_SHARE_PURCHASE_PINTEREST,
            self::EVENT_EMAIL_FRIEND,
            self::EVENT_INACTIVITY,
            self::EVENT_PUSH_NOTIFICATION,
            self::EVENT_AFFILIATE_JOIN,
            self::EVENT_TIER_UP,
            self::EVENT_TIER_DOWN,
            self::EVENT_RMA_CREATED,
            self::EVENT_LIFETIME_AMOUNT,
            self::EVENT_POINTS_ALLOCATION,
            self::EVENT_REFER_SIGNUP,
            self::EVENT_REFER_ORDER,
        ];
    }

    /**
     * Get options as array — built-in events merged with admin-configured custom events
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => self::EVENT_SIGNUP, 'label' => __('Customer Sign Up')],
            ['value' => self::EVENT_FIRST_ORDER, 'label' => __('First Order')],
            ['value' => self::EVENT_PLACE_ORDER, 'label' => __('Place an Order')],
            ['value' => self::EVENT_NEWSLETTER, 'label' => __('Newsletter Subscription')],
            ['value' => self::EVENT_REVIEW, 'label' => __('Write a Product Review')],
            ['value' => self::EVENT_BIRTHDAY, 'label' => __('Customer Birthday')],
            ['value' => self::EVENT_FACEBOOK_LIKE, 'label' => __('Facebook Like')],
            ['value' => self::EVENT_TWITTER_TWEET, 'label' => __('Twitter Tweet')],
            ['value' => self::EVENT_PINTEREST_PIN, 'label' => __('Pinterest Pin')],
            ['value' => self::EVENT_SHARE_PURCHASE_FACEBOOK, 'label' => __('Share Purchase on Facebook')],
            ['value' => self::EVENT_SHARE_PURCHASE_TWITTER, 'label' => __('Share Purchase on Twitter')],
            ['value' => self::EVENT_SHARE_PURCHASE_PINTEREST, 'label' => __('Share Purchase on Pinterest')],
            ['value' => self::EVENT_EMAIL_FRIEND, 'label' => __('Email a Friend')],
            ['value' => self::EVENT_INACTIVITY, 'label' => __('Customer Inactivity Bonus')],
            ['value' => self::EVENT_PUSH_NOTIFICATION, 'label' => __('Push Notification Opt-In')],
            ['value' => self::EVENT_AFFILIATE_JOIN, 'label' => __('Affiliate Program Join')],
            ['value' => self::EVENT_TIER_UP, 'label' => __('Tier Level Up')],
            ['value' => self::EVENT_TIER_DOWN, 'label' => __('Tier Level Down')],
            ['value' => self::EVENT_RMA_CREATED, 'label' => __('RMA Created')],
            ['value' => self::EVENT_LIFETIME_AMOUNT, 'label' => __('Lifetime Spending Milestone')],
            ['value' => self::EVENT_POINTS_ALLOCATION, 'label' => __('Manual Points Allocation')],
            ['value' => self::EVENT_REFER_SIGNUP, 'label' => __('Referral Sign Up')],
            ['value' => self::EVENT_REFER_ORDER, 'label' => __('Referral Order')],
        ];

        // Merge admin-configured custom behavior events (one "code,Label" per line)
        $customRaw = trim($this->config->getCustomBehaviorEvents());

        if (!empty($customRaw)) {
            foreach (explode("\n", $customRaw) as $line) {
                $parts = explode(',', trim($line), 2);

                if (count($parts) === 2) {
                    $code  = trim($parts[0]);
                    $label = trim($parts[1]);

                    if ($code !== '' && $label !== '') {
                        $options[] = ['value' => $code, 'label' => $label];
                    }
                }
            }
        }

        return $options;
    }
}
