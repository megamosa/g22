<?php
/**
 * MagoArab_EasYorder Order State Source Model
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
use Magento\Sales\Model\Order;

/**
 * Class OrderState
 * 
 * Provides order state options
 */
class OrderState implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Use System Default --')],
            ['value' => Order::STATE_NEW, 'label' => __('New')],
            ['value' => Order::STATE_PENDING_PAYMENT, 'label' => __('Pending Payment')],
            ['value' => Order::STATE_PROCESSING, 'label' => __('Processing')],
            ['value' => Order::STATE_COMPLETE, 'label' => __('Complete')],
            ['value' => Order::STATE_CLOSED, 'label' => __('Closed')],
            ['value' => Order::STATE_CANCELED, 'label' => __('Canceled')],
            ['value' => Order::STATE_HOLDED, 'label' => __('On Hold')],
            ['value' => Order::STATE_PAYMENT_REVIEW, 'label' => __('Payment Review')]
        ];
    }
}