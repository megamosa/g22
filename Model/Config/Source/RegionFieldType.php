<?php
declare(strict_types=1);

namespace MagoArab\EasYorder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RegionFieldType implements OptionSourceInterface
{
    public const FIELD_HIDDEN = 'hidden';
    public const FIELD_VISIBLE = 'visible';
    public const FIELD_REQUIRED = 'required';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIELD_HIDDEN, 'label' => __('Hidden')],
            ['value' => self::FIELD_VISIBLE, 'label' => __('Visible (Optional)')],
            ['value' => self::FIELD_REQUIRED, 'label' => __('Visible (Required)')]
        ];
    }
}