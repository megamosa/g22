<?php
/**
 * MagoArab_EasYorder Quick Order Data Interface
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Api\Data;

/**
 * Interface QuickOrderDataInterface
 * 
 * Data interface for quick order information
 */
interface QuickOrderDataInterface
{
    public const PRODUCT_ID = 'product_id';
    public const QTY = 'qty';
    public const CUSTOMER_NAME = 'customer_name';
    public const CUSTOMER_PHONE = 'customer_phone';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const ADDRESS = 'address';
    public const CITY = 'city';
    public const COUNTRY_ID = 'country_id';
    public const REGION = 'region';
    public const POSTCODE = 'postcode';
    public const SHIPPING_METHOD = 'shipping_method';
    public const PAYMENT_METHOD = 'payment_method';

    /**
     * Get product ID
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set product ID
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * Get quantity
     *
     * @return int
     */
    public function getQty(): int;

    /**
     * Set quantity
     *
     * @param int $qty
     * @return $this
     */
    public function setQty(int $qty): self;

    /**
     * Get customer name
     *
     * @return string
     */
    public function getCustomerName(): string;

    /**
     * Set customer name
     *
     * @param string $name
     * @return $this
     */
    public function setCustomerName(string $name): self;

    /**
     * Get customer phone
     *
     * @return string
     */
    public function getCustomerPhone(): string;

    /**
     * Set customer phone
     *
     * @param string $phone
     * @return $this
     */
    public function setCustomerPhone(string $phone): self;

    /**
     * Get customer email
     *
     * @return string|null
     */
    public function getCustomerEmail(): ?string;

    /**
     * Set customer email
     *
     * @param string|null $email
     * @return $this
     */
    public function setCustomerEmail(?string $email): self;

    /**
     * Get address
     *
     * @return string
     */
    public function getAddress(): string;

    /**
     * Set address
     *
     * @param string $address
     * @return $this
     */
    public function setAddress(string $address): self;

    /**
     * Get city
     *
     * @return string
     */
    public function getCity(): string;

    /**
     * Set city
     *
     * @param string $city
     * @return $this
     */
    public function setCity(string $city): self;

    /**
     * Get country ID
     *
     * @return string
     */
    public function getCountryId(): string;

    /**
     * Set country ID
     *
     * @param string $countryId
     * @return $this
     */
    public function setCountryId(string $countryId): self;

    /**
     * Get region
     *
     * @return string|null
     */
    public function getRegion(): ?string;

    /**
     * Set region
     *
     * @param string|null $region
     * @return $this
     */
    public function setRegion(?string $region): self;

    /**
     * Get postcode
     *
     * @return string|null
     */
    public function getPostcode(): ?string;

    /**
     * Set postcode
     *
     * @param string|null $postcode
     * @return $this
     */
    public function setPostcode(?string $postcode): self;

    /**
     * Get shipping method
     *
     * @return string
     */
    public function getShippingMethod(): string;

    /**
     * Set shipping method
     *
     * @param string $shippingMethod
     * @return $this
     */
    public function setShippingMethod(string $shippingMethod): self;

    /**
     * Get payment method
     *
     * @return string
     */
    public function getPaymentMethod(): string;

    /**
     * Set payment method
     *
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod(string $paymentMethod): self;

    /**
     * Get super attribute
     *
     * @return array|null
     */
    public function getSuperAttribute(): ?array;

    /**
     * Set super attribute
     *
     * @param array $superAttribute
     * @return $this
     */
    public function setSuperAttribute(array $superAttribute): QuickOrderDataInterface;
}