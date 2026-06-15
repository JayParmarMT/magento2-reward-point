<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Customer;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Block for rendering referral code field on customer registration form
 */
class ReferralField extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Suppress output when module is disabled
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Get field label
     *
     * @return string
     */
    public function getFieldLabel(): string
    {
        return (string) __('Referral Code (Optional)');
    }

    /**
     * Get field name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'meetanshi_referral_code';
    }

    /**
     * Get field ID
     *
     * @return string
     */
    public function getFieldId(): string
    {
        return 'meetanshi-referral-code';
    }

    /**
     * Get placeholder text
     *
     * @return string
     */
    public function getPlaceholder(): string
    {
        return (string) __('Enter referral code or email');
    }

    /**
     * Get the template file
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Meetanshi_RewardPoints::customer/referral_field.phtml';
    }
}
