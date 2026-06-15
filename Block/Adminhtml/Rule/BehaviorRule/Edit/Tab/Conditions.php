<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Rule\BehaviorRule\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as ConditionsBlock;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleConditionFactory;

/**
 * Conditions tab block for Rule form
 */
class Conditions extends Generic implements TabInterface
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param ConditionsBlock $conditionsBlock
     * @param Fieldset $rendererFieldset
     * @param BehaviorRuleConditionFactory $ruleConditionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        private readonly ConditionsBlock $conditionsBlock,
        private readonly Fieldset $rendererFieldset,
        private readonly BehaviorRuleConditionFactory $ruleConditionFactory,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    public function getTabLabel(): string
    {
        return (string) __('Conditions');
    }

    public function getTabTitle(): string
    {
        return (string) __('Conditions');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getTabClass(): string
    {
        return '';
    }

    public function getTabUrl(): string
    {
        return '';
    }

    public function isAjaxLoaded(): bool
    {
        return false;
    }

    protected function _prepareForm()
    {
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        $model  = $this->ruleConditionFactory->create();

        if ($ruleId) {
            $model->load($ruleId);
        }

        $formName             = 'meetanshi_rewardpoints_behavior_rule_form';
        $conditionsFieldSetId = $model->getConditionsFieldSetId($formName);

        $newChildUrl = $this->getUrl(
            'meetanshi_rewardpoints/behaviorrule/newConditionHtml/form/' . $conditionsFieldSetId,
            ['form_namespace' => $formName],
        );

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate('Meetanshi_RewardPoints::rule/fieldset.phtml')
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId($conditionsFieldSetId);

        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('behavior_rule_');

        $fieldset = $form->addFieldset(
            $conditionsFieldSetId,
            ['legend' => __('Apply the rule only if the following conditions are met (leave blank for all customers).')],
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions' . $conditionsFieldSetId,
            'text',
            [
                'name'           => 'conditions',
                'label'          => __('Conditions'),
                'title'          => __('Conditions'),
                'required'       => false,
                'data-form-part' => $formName,
            ],
        )->setRule($model)->setRenderer($this->conditionsBlock);

        $this->setConditionFormName($model->getConditions(), $formName, $conditionsFieldSetId);
        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function setConditionFormName(
        AbstractCondition $conditions,
        string $formName,
        ?string $fieldsetId,
    ): void {
        $conditions->setFormName($formName);

        if ($fieldsetId !== null) {
            $conditions->setJsFormObject($fieldsetId);
        }

        if ($conditions->getConditions() && is_array($conditions->getConditions())) {
            foreach ($conditions->getConditions() as $condition) {
                $this->setConditionFormName($condition, $formName, $fieldsetId);
            }
        }
    }
}
