<?php
/**
 * MagoArab_EasYorder Postcode Generation Method Source Model
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
 * Class PostcodeGenerationMethod
 * 
 * Provides postcode generation method options
 */
class PostcodeGenerationMethod implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'last_5_digits', 'label' => __('Last 5 digits of phone number')],
            ['value' => 'area_code_based', 'label' => __('Area code based (Egyptian governorates)')],
            ['value' => 'hash_based', 'label' => __('Hash-based generation')],
            ['value' => 'sequential', 'label' => __('Sequential numbering (10001, 10002, etc.)')]
        ];
    }
}