<?php
/**
 * MagoArab_EasYorder Order Status Source Model
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
use Magento\Sales\Model\Config\Source\Order\Status;

/**
 * Class OrderStatus
 * 
 * Provides order status options including custom statuses
 */
class OrderStatus implements OptionSourceInterface
{
    /**
     * @var Status
     */
    private $orderStatusSource;

    /**
     * Constructor
     */
    public function __construct(Status $orderStatusSource)
    {
        $this->orderStatusSource = $orderStatusSource;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        // Get all available order statuses
        $statuses = $this->orderStatusSource->toOptionArray();
        
        // Add default option at the beginning
        array_unshift($statuses, [
            'value' => '',
            'label' => __('-- Use System Default --')
        ]);
        
        return $statuses;
    }
}