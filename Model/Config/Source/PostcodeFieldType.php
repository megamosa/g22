<?php
/**
 * MagoArab_EasYorder Postcode Field Type Source Model
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
 * Class PostcodeFieldType
 * 
 * Provides postcode field display type options
 */
class PostcodeFieldType implements OptionSourceInterface
{
    public const FIELD_HIDDEN = 'hidden';
    public const FIELD_OPTIONAL = 'optional';
    public const FIELD_REQUIRED = 'required';

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIELD_HIDDEN, 'label' => __('Hidden (Do not show)')],
            ['value' => self::FIELD_OPTIONAL, 'label' => __('Optional (Show but not required)')],
            ['value' => self::FIELD_REQUIRED, 'label' => __('Required (Show and required)')]
        ];
    }
}