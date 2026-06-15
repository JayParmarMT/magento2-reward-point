<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Ui\Component;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\Filters;
use Magento\Ui\Component\Listing\Columns\ColumnInterface;
use Meetanshi\RewardPoints\Ui\Component\Filters\Type\SelectLike;

/**
 * Adds 'selectLike' filter type support to the grid Filters component.
 *
 * Columns with <filter>selectLike</filter> will get a SelectLike filter
 * that applies a LIKE condition (for GROUP_CONCAT columns such as websites
 * and customer_groups).
 *
 * Uses ObjectManager::create() directly to avoid registering a custom
 * UI component type in definition.xml (which requires an XSD-whitelisted
 * element name and causes an "Invalid Document" error on di:compile).
 */
class FiltersPlugin
{
    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    /**
     * Intercept update() to handle selectLike filter type
     *
     * @param Filters $subject
     * @param callable $proceed
     * @param UiComponentInterface $component
     * @return void
     */
    public function aroundUpdate(Filters $subject, callable $proceed, UiComponentInterface $component): void
    {
        if (!($component instanceof ColumnInterface)) {
            $proceed($component);
            return;
        }

        $filterType = $component->getData('config/filter');

        if (is_array($filterType)) {
            $filterType = $filterType['filterType'];
        }

        if ($filterType !== 'selectLike') {
            $proceed($component);
            return;
        }

        /** @var SelectLike $filterComponent */
        $filterComponent = $this->objectManager->create(
            SelectLike::class,
            ['context' => $subject->getContext()],
        );
        $filterComponent->setData('config', $component->getConfiguration());
        $filterComponent->prepare();
        $subject->addComponent($component->getName(), $filterComponent);
    }
}
