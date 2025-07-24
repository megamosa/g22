<?php
/**
 * MagoArab_EasYorder Form Position Source Model
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class FormPosition
 * 
 * Provides position options for the quick order form display
 */
class FormPosition implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'after_review', 'label' => __('After Product Reviews')],
            ['value' => 'before_review', 'label' => __('Before Product Reviews')],
            ['value' => 'after_info', 'label' => __('After Product Info')],
            ['value' => 'bottom_page', 'label' => __('Bottom of Page')]
        ];
    }
}