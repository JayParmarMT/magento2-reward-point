<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\ReferralRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\ConditionInterface;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleConditionFactory;

/**
 * Renders a new condition row HTML for the conditions tree
 */
class NewConditionHtml extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::reward_points';

    /**
     * @param Context $context
     * @param ReferralRuleConditionFactory $referralRuleConditionFactory
     */
    public function __construct(
        Context $context,
        private readonly ReferralRuleConditionFactory $referralRuleConditionFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Render new condition HTML
     *
     * @return void
     */
    public function execute(): void
    {
        $objectId      = $this->getRequest()->getParam('id');
        $formNamespace = $this->getRequest()->getParam('form_namespace');
        $types         = explode('|', str_replace('-', '/', (string) $this->getRequest()->getParam('type', '')));
        $objectType    = $types[0];
        $responseBody  = '';

        if (class_exists($objectType) && !in_array(ConditionInterface::class, class_implements($objectType) ?: [])) {
            $this->getResponse()->setBody($responseBody);
            return;
        }

        $rule = $this->referralRuleConditionFactory->create();

        /** @var AbstractCondition $conditionModel */
        $conditionModel = $this->_objectManager->create($objectType)
            ->setId($objectId)
            ->setType($objectType)
            ->setRule($rule)
            ->setPrefix('conditions');

        if (!empty($types[1])) {
            $conditionModel->setAttribute($types[1]);
        }

        if ($conditionModel instanceof AbstractCondition) {
            $conditionModel->setJsFormObject($this->getRequest()->getParam('form'));
            $conditionModel->setFormName($formNamespace);
            $responseBody = $conditionModel->asHtmlRecursive();
        }

        $this->getResponse()->setBody($responseBody);
    }
}
