<?php
/**
 * MagoArab_EasYorder Quick Order Data Model
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Model\Data;

use MagoArab\EasYorder\Api\Data\QuickOrderDataInterface;
use Magento\Framework\DataObject;

/**
 * Class QuickOrderData
 * 
 * Data model for quick order information
 */
class QuickOrderData extends DataObject implements QuickOrderDataInterface
{
    const SUPER_ATTRIBUTE = 'super_attribute';
    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return (int)$this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId(int $productId): QuickOrderDataInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getQty(): int
    {
        return (int)$this->getData(self::QTY) ?: 1;
    }

    /**
     * @inheritDoc
     */
    public function setQty(int $qty): QuickOrderDataInterface
    {
        return $this->setData(self::QTY, $qty);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerName(): string
    {
        return (string)$this->getData(self::CUSTOMER_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerName(string $name): QuickOrderDataInterface
    {
        return $this->setData(self::CUSTOMER_NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerPhone(): string
    {
        return (string)$this->getData(self::CUSTOMER_PHONE);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerPhone(string $phone): QuickOrderDataInterface
    {
        return $this->setData(self::CUSTOMER_PHONE, $phone);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerEmail(): ?string
    {
        return $this->getData(self::CUSTOMER_EMAIL);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerEmail(?string $email): QuickOrderDataInterface
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    /**
     * @inheritDoc
     */
    public function getAddress(): string
    {
        return (string)$this->getData(self::ADDRESS);
    }

    /**
     * @inheritDoc
     */
    public function setAddress(string $address): QuickOrderDataInterface
    {
        return $this->setData(self::ADDRESS, $address);
    }

    /**
     * @inheritDoc
     */
    public function getCity(): string
    {
        return (string)$this->getData(self::CITY);
    }

    /**
     * @inheritDoc
     */
    public function setCity(string $city): QuickOrderDataInterface
    {
        return $this->setData(self::CITY, $city);
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(): string
    {
        return (string)$this->getData(self::COUNTRY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCountryId(string $countryId): QuickOrderDataInterface
    {
        return $this->setData(self::COUNTRY_ID, $countryId);
    }

    /**
     * @inheritDoc
     */
    public function getRegion(): ?string
    {
        return $this->getData(self::REGION);
    }

    /**
     * @inheritDoc
     */
    public function setRegion(?string $region): QuickOrderDataInterface
    {
        return $this->setData(self::REGION, $region);
    }

    /**
     * @inheritDoc
     */
    public function getPostcode(): ?string
    {
        return $this->getData(self::POSTCODE);
    }

    /**
     * @inheritDoc
     */
    public function setPostcode(?string $postcode): QuickOrderDataInterface
    {
        return $this->setData(self::POSTCODE, $postcode);
    }

    /**
     * @inheritDoc
     */
    public function getShippingMethod(): string
    {
        return (string)$this->getData(self::SHIPPING_METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setShippingMethod(string $shippingMethod): QuickOrderDataInterface
    {
        return $this->setData(self::SHIPPING_METHOD, $shippingMethod);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethod(): string
    {
        return (string)$this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentMethod(string $paymentMethod): QuickOrderDataInterface
    {
        return $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    /**
     * @inheritDoc
     */
    public function getSuperAttribute(): ?array
    {
        return $this->getData(self::SUPER_ATTRIBUTE);
    }

    /**
     * @inheritDoc
     */
    public function setSuperAttribute(array $superAttribute): QuickOrderDataInterface
    {
        return $this->setData(self::SUPER_ATTRIBUTE, $superAttribute);
    }
}