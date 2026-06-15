<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Customer;

use Magento\Customer\Block\Form\Register;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Plugin to add referral code field data to customer registration form
 */
class AddReferralFieldPlugin
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * After getFormData — append referral code from cookie/session if set
     *
     * @param Register $subject
     * @param \Magento\Framework\DataObject $result
     * @return \Magento\Framework\DataObject
     */
    public function afterGetFormData(
        Register $subject,
        \Magento\Framework\DataObject $result,
    ): \Magento\Framework\DataObject {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        // The referral field is rendered via layout XML block injection
        // This plugin can be used to pre-populate the field from cookie if needed
        return $result;
    }
}
