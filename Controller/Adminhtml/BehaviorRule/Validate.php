<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\BehaviorRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Behavior Rule Validate Controller — returns success JSON for UI form validateUrl.
 */
class Validate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::behavior_rule';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Always return success — actual validation is done client-side or in Save.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        return $this->jsonFactory->create()->setData(['error' => false]);
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
