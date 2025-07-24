<?php
/**
 * MagoArab_EasYorder Quick Order Service Interface
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Api;

use MagoArab\EasYorder\Api\Data\QuickOrderDataInterface;

/**
 * Interface QuickOrderServiceInterface
 * 
 * Service contract for quick order operations
 */
interface QuickOrderServiceInterface
{
    /**
     * Get available shipping methods for product
     *
     * @param int $productId
     * @param string $countryId
     * @param string|null $region
     * @param string|null $postcode
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAvailableShippingMethods(
        int $productId,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null
    ): array;

    /**
     * Get available payment methods
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAvailablePaymentMethods(): array;

    /**
     * Create quick order
     *
     * @param \MagoArab\EasYorder\Api\Data\QuickOrderDataInterface $orderData
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createQuickOrder(QuickOrderDataInterface $orderData): array;

    /**
     * Calculate shipping cost
     *
     * @param int $productId
     * @param string $shippingMethod
     * @param string $countryId
     * @param string|null $region
     * @param string|null $postcode
     * @param int $qty
     * @return float
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function calculateShippingCost(
        int $productId,
        string $shippingMethod,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null,
        int $qty = 1
    ): float;

    /**
     * Calculate order total with dynamic catalog and pricing rules applied
     *
     * @param int $productId
     * @param int $qty
     * @param string $shippingMethod
     * @param string $countryId
     * @param string|null $region
     * @param string|null $postcode
     * @return array
     */
    public function calculateOrderTotalWithDynamicRules(
        int $productId,
        int $qty,
        string $shippingMethod,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null
    ): array;
}