<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Color picker renderer for system configuration fields
 */
class ColorPicker extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Meetanshi_RewardPoints::system/config/color_picker.phtml';

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setData('html_id', $element->getHtmlId());
        $this->setData('name', $element->getName());
        $this->setData('value', $element->getValue() ?: '#000000');

        return $this->_toHtml();
    }
}
