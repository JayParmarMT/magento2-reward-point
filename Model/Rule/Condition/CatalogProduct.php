<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Condition;

use Magento\CatalogRule\Model\Rule\Condition\Product as AbstractProduct;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Catalog product condition for reward points catalog rule
 */
class CatalogProduct extends AbstractProduct
{
    /**
     * Get attribute element HTML with proper context
     *
     * @return string
     */
    public function getAttributeElementHtml(): string
    {
        $element = $this->getAttributeElement();

        return $element->getHtml();
    }

    /**
     * Get type element HTML
     *
     * @return string
     */
    public function getTypeElementHtml(): string
    {
        return $this->getTypeElement()->getHtml();
    }
}
