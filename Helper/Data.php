<?php
/**
 * MagoArab_EasYorder Helper Data - COMPLETE FIXED VERSION
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    // General Settings
    private const XML_PATH_ENABLED = 'magoarab_easyorder/general/enabled';
    private const XML_PATH_FORM_TITLE = 'magoarab_easyorder/general/form_title';
    private const XML_PATH_SUCCESS_MESSAGE = 'magoarab_easyorder/general/success_message';
    private const XML_PATH_EMAIL_NOTIFICATION = 'magoarab_easyorder/general/send_email_notification';
    private const XML_PATH_CUSTOMER_GROUP = 'magoarab_easyorder/general/default_customer_group';
    private const XML_PATH_FORM_POSITION = 'magoarab_easyorder/general/form_position';
    private const XML_PATH_AUTO_GENERATE_EMAIL = 'magoarab_easyorder/general/auto_generate_email';
    private const XML_PATH_PHONE_VALIDATION = 'magoarab_easyorder/general/phone_validation';
    private const XML_PATH_EMAIL_DOMAIN = 'magoarab_easyorder/general/email_domain';
    
    // Form Fields Settings
    private const XML_PATH_REQUIRE_EMAIL = 'magoarab_easyorder/form_fields/require_email';
    private const XML_PATH_REQUIRE_POSTCODE = 'magoarab_easyorder/form_fields/require_postcode';
    private const XML_PATH_REQUIRE_REGION = 'magoarab_easyorder/form_fields/require_region';
    private const XML_PATH_SHOW_STREET_2 = 'magoarab_easyorder/form_fields/show_street_2';
    private const XML_PATH_REQUIRE_CITY = 'magoarab_easyorder/form_fields/require_city';
    // حذف هذه الثوابت
    // private const XML_PATH_COUNTRY_FIELD_TYPE = 'magoarab_easyorder/form_fields/country_field_type';
    // private const XML_PATH_DEFAULT_COUNTRY = 'magoarab_easyorder/form_fields/default_country';

    // إضافة ثابت جديد للـ CSS المخصص
    // إضافة هذا السطر مع باقي الثوابت في بداية الكلاس
    private const XML_PATH_CUSTOM_CSS = 'magoarab_easyorder/form_fields/custom_css';
    private const XML_PATH_REGION_FIELD_TYPE = 'magoarab_easyorder/form_fields/region_field_type';
    
    // Postcode Generation Settings
    private const XML_PATH_AUTO_GENERATE_POSTCODE = 'magoarab_easyorder/postcode_generation/auto_generate_postcode';
    private const XML_PATH_POSTCODE_GENERATION_METHOD = 'magoarab_easyorder/postcode_generation/postcode_generation_method';
    
    // Shipping & Payment Settings
    private const XML_PATH_ENABLED_SHIPPING_METHODS = 'magoarab_easyorder/shipping_payment/enabled_shipping_methods';
    private const XML_PATH_SHIPPING_METHOD_PRIORITY = 'magoarab_easyorder/shipping_payment/shipping_method_priority';
    private const XML_PATH_FALLBACK_SHIPPING_PRICE = 'magoarab_easyorder/shipping_payment/fallback_shipping_price';
    private const XML_PATH_ENABLED_PAYMENT_METHODS = 'magoarab_easyorder/shipping_payment/enabled_payment_methods';
    private const XML_PATH_DEFAULT_PAYMENT_METHOD = 'magoarab_easyorder/shipping_payment/default_payment_method';
    private const XML_PATH_PAYMENT_METHOD_PRIORITY = 'magoarab_easyorder/shipping_payment/payment_method_priority';
    
    // Legacy Settings
    private const XML_PATH_FORCE_FALLBACK_SHIPPING = 'magoarab_easyorder/shipping/force_fallback_shipping';
    private const XML_PATH_DEFAULT_SHIPPING_PRICE = 'magoarab_easyorder/shipping/default_shipping_price';
    private const XML_PATH_FREE_SHIPPING_THRESHOLD = 'magoarab_easyorder/shipping/free_shipping_threshold';
	
		// Order Configuration
	private const XML_PATH_DEFAULT_ORDER_STATUS = 'magoarab_easyorder/shipping_payment/default_order_status';
	private const XML_PATH_DEFAULT_ORDER_STATE = 'magoarab_easyorder/shipping_payment/default_order_state';
	private const XML_PATH_POSTCODE_FIELD_TYPE = 'magoarab_easyorder/form_fields/postcode_field_type';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
    }

    /**
     * Check if module is enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get form title
     */
    public function getFormTitle(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FORM_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get success message
     */
    public function getSuccessMessage(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_SUCCESS_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if email notification is enabled
     */
    public function isEmailNotificationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EMAIL_NOTIFICATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get default customer group
     */
    public function getDefaultCustomerGroup(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_GROUP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get form position
     */
    public function getFormPosition(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FORM_POSITION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if auto generate email is enabled
     */
    public function isAutoGenerateEmailEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_GENERATE_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if phone validation is enabled
     */
    public function isPhoneValidationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PHONE_VALIDATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get email domain for auto-generated emails
     */
    public function getEmailDomain(?int $storeId = null): string
    {
        $domain = $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_DOMAIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $domain ?: 'easypay.com';
    }

    /**
     * Check if email field is required
     */
    public function isEmailRequired(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if postcode field is required
     */
    public function isPostcodeRequired(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_POSTCODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if region field is required
     */
    public function isRegionRequired(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_REGION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if second street line should be shown
     */
    public function showStreet2(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_STREET_2,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if city field is required
     */
    public function isCityRequired(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_REQUIRE_CITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if fallback shipping should be forced
     */
    public function isForceFallbackShipping(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FORCE_FALLBACK_SHIPPING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get default shipping price
     */
    public function getDefaultShippingPrice(?int $storeId = null): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_SHIPPING_PRICE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get fallback shipping price with enhanced logic
     */
    public function getFallbackShippingPrice(?int $storeId = null): float
    {
        // Try to get flatrate price first
        $flatratePrice = $this->scopeConfig->getValue(
            'carriers/flatrate/price',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if ($flatratePrice && $flatratePrice > 0) {
            return (float)$flatratePrice;
        }
        
        // Try fallback shipping price
        $fallbackPrice = $this->scopeConfig->getValue(
            self::XML_PATH_FALLBACK_SHIPPING_PRICE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if ($fallbackPrice && $fallbackPrice > 0) {
            return (float)$fallbackPrice;
        }
        
        // Try legacy default shipping price
        $defaultPrice = $this->getDefaultShippingPrice($storeId);
        if ($defaultPrice > 0) {
            return $defaultPrice;
        }
        
        // Ultimate fallback
        return 25.0;
    }



    /**
     * Check if free shipping should be applied
     */
    public function shouldApplyFreeShipping(float $subtotal, ?int $storeId = null): bool
    {
        $threshold = $this->getFreeShippingThreshold($storeId);
        return $threshold > 0 && $subtotal >= $threshold;
    }

    /**
     * Get enabled shipping methods
     */
    public function getEnabledShippingMethods(?int $storeId = null): array
    {
        $methods = $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED_SHIPPING_METHODS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($methods)) {
            return [];
        }
        
        return explode(',', $methods);
    }

    /**
     * Get shipping method priority
     */
    public function getShippingMethodPriority(?int $storeId = null): array
    {
        $priority = $this->scopeConfig->getValue(
            self::XML_PATH_SHIPPING_METHOD_PRIORITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($priority)) {
            return [];
        }
        
        return explode(',', $priority);
    }

    /**
     * Get enabled payment methods
     */
    public function getEnabledPaymentMethods(?int $storeId = null): array
    {
        $methods = $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED_PAYMENT_METHODS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($methods)) {
            return [];
        }
        
        return explode(',', $methods);
    }

    /**
     * Get default payment method
     */
    public function getDefaultPaymentMethod(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_PAYMENT_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get payment method priority
     */
    public function getPaymentMethodPriority(?int $storeId = null): array
    {
        $priority = $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_METHOD_PRIORITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        if (empty($priority)) {
            return [];
        }
        
        return explode(',', $priority);
    }

    /**
     * Generate guest email from phone number using configured domain
     */
    public function generateGuestEmail(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $domain = $this->getEmailDomain();
        return $cleanPhone . '@' . $domain;
    }

    /**
     * Format and validate phone number
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add Egyptian country code if not present
        if (!str_starts_with($cleanPhone, '+') && !str_starts_with($cleanPhone, '20')) {
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '+2' . $cleanPhone;
            } else {
                $cleanPhone = '+20' . $cleanPhone;
            }
        }
        
        return $cleanPhone;
    }

    /**
     * Validate phone number format
     */
    public function validatePhoneNumber(string $phone): bool
    {
        if (!$this->isPhoneValidationEnabled()) {
            return true;
        }
        
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Egyptian phone number validation
        $patterns = [
            '/^\+20[0-9]{10}$/',  // +20xxxxxxxxxx
            '/^20[0-9]{10}$/',    // 20xxxxxxxxxx
            '/^0[0-9]{10}$/',     // 0xxxxxxxxxx
            '/^[0-9]{11}$/'       // xxxxxxxxxxx
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanPhone)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * ENHANCED shipping methods filter - supports ALL extensions
     */
    public function filterShippingMethods(array $methods): array
    {
        // SIMPLE APPROACH: Return all methods without filtering
        // This ensures compatibility with ALL shipping extensions
        return $methods;
        
        /* 
        // Advanced filtering can be enabled later if needed:
        $enabledMethods = $this->getEnabledShippingMethods();
        
        if (empty($enabledMethods)) {
            return $methods; // No filtering configured
        }
        
        // Filter logic here...
        */
    }

    /**
     * ENHANCED payment methods filter - supports ALL extensions  
     */
    public function filterPaymentMethods(array $methods): array
    {
        $enabledMethods = $this->getEnabledPaymentMethods();
        $priority = $this->getPaymentMethodPriority();
        
        // Filter enabled methods
        if (!empty($enabledMethods)) {
            $methods = array_filter($methods, function($method) use ($enabledMethods) {
                return in_array($method['code'], $enabledMethods);
            });
        }
        
        // Sort by priority
        if (!empty($priority)) {
            usort($methods, function($a, $b) use ($priority) {
                $aPriority = array_search($a['code'], $priority);
                $bPriority = array_search($b['code'], $priority);
                
                if ($aPriority === false) $aPriority = 999;
                if ($bPriority === false) $bPriority = 999;
                
                return $aPriority <=> $bPriority;
            });
        }
        
        return $methods;
    }

    /**
     * Check if shipping method is enabled in configuration
     */
    public function isShippingMethodEnabled(string $methodCode): bool
    {
        $enabledMethods = $this->getEnabledShippingMethods();
        
        if (empty($enabledMethods)) {
            return true; // All enabled if none specified
        }
        
        return in_array($methodCode, $enabledMethods);
    }

    /**
     * Check if payment method is enabled in configuration
     */
    public function isPaymentMethodEnabled(string $methodCode): bool
    {
        $enabledMethods = $this->getEnabledPaymentMethods();
        
        if (empty($enabledMethods)) {
            return true; // All enabled if none specified
        }
        
        return in_array($methodCode, $enabledMethods);
    }

    /**
     * Check if auto generate postcode is enabled
     */
    public function isAutoGeneratePostcodeEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_GENERATE_POSTCODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get postcode generation method
     */
    public function getPostcodeGenerationMethod(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_POSTCODE_GENERATION_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'last_5_digits';
    }

    /**
     * Generate postcode from phone number
     */
    public function generatePostcodeFromPhone(string $phone, ?int $storeId = null): string
    {
        if (!$this->isAutoGeneratePostcodeEnabled($storeId)) {
            return '';
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $method = $this->getPostcodeGenerationMethod($storeId);

        switch ($method) {
            case 'last_5_digits':
                return $this->generatePostcodeFromLastDigits($cleanPhone);
            case 'area_code_based':
                return $this->generatePostcodeFromAreaCode($cleanPhone);
            case 'hash_based':
                return $this->generatePostcodeFromHash($cleanPhone);
            case 'sequential':
                return $this->generateSequentialPostcode();
            default:
                return $this->generatePostcodeFromLastDigits($cleanPhone);
        }
    }
/**
 * Get default order status
 */
public function getDefaultOrderStatus(?int $storeId = null): string
{
    return (string)$this->scopeConfig->getValue(
        self::XML_PATH_DEFAULT_ORDER_STATUS,
        ScopeInterface::SCOPE_STORE,
        $storeId
    );
}

/**
 * Get default order state
 */
public function getDefaultOrderState(?int $storeId = null): string
{
    return (string)$this->scopeConfig->getValue(
        self::XML_PATH_DEFAULT_ORDER_STATE,
        ScopeInterface::SCOPE_STORE,
        $storeId
    );
}

/**
 * Get postcode field type
 */
public function getPostcodeFieldType(?int $storeId = null): string
{
    return (string)$this->scopeConfig->getValue(
        self::XML_PATH_POSTCODE_FIELD_TYPE,
        ScopeInterface::SCOPE_STORE,
        $storeId
    ) ?: 'optional';
}

/**
 * Check if postcode field should be hidden
 */
public function isPostcodeFieldHidden(?int $storeId = null): bool
{
    return $this->getPostcodeFieldType($storeId) === 'hidden';
}

/**
 * Check if postcode field is required (overrides country settings)
 */
public function isPostcodeFieldRequired(?int $storeId = null): bool
{
    return $this->getPostcodeFieldType($storeId) === 'required';
}
    /**
     * Generate postcode from last 5 digits of phone
     */
    private function generatePostcodeFromLastDigits(string $cleanPhone): string
    {
        if (strlen($cleanPhone) >= 5) {
            return substr($cleanPhone, -5);
        }
        return str_pad($cleanPhone, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate postcode based on Egyptian area codes
     */
    private function generatePostcodeFromAreaCode(string $cleanPhone): string
    {
        // Egyptian area code mapping
        $areaCodes = [
            '02' => '11511',  // Cairo
            '03' => '21511',  // Alexandria
            '040' => '31511', // Aswan
            '045' => '81511', // Luxor
            '046' => '71511', // Qena
            '047' => '83511', // Sohag
            '050' => '61511', // Mansoura
            '055' => '35511', // Zagazig
            '057' => '44511', // Damietta
            '062' => '13511', // Suez
            '064' => '41511', // Ismailia
            '065' => '15511', // Red Sea
            '066' => '45511', // Port Said
            '068' => '31111', // North Sinai
            '069' => '46511', // South Sinai
            '082' => '73511', // Minya
            '084' => '61111', // Fayoum
            '086' => '62511', // Beni Suef
            '088' => '12511', // Giza
            '092' => '33511', // Wadi Gedid
            '093' => '84511', // Assiut
            '095' => '27511', // Matrouh
            '097' => '22511', // Beheira
            '040' => '64511', // Kafr El Sheikh
            '047' => '32511', // Gharbia
            '048' => '31611', // Menoufia
            '013' => '42511', // Qalyubia
            '055' => '44611', // Sharqia
            '057' => '34511'  // Dakahlia
        ];

        // Extract area code from phone
        foreach ($areaCodes as $code => $postcode) {
            if (str_starts_with($cleanPhone, $code) || str_starts_with($cleanPhone, '20' . $code)) {
                return $postcode;
            }
        }

        // Default fallback
        return '11511';
    }

    /**
     * Generate postcode using hash method
     */
    private function generatePostcodeFromHash(string $cleanPhone): string
    {
        $hash = md5($cleanPhone);
        $numeric = preg_replace('/[^0-9]/', '', $hash);
        return substr($numeric . '00000', 0, 5);
    }

    /**
     * Generate sequential postcode
     */
    private function generateSequentialPostcode(): string
    {
        // Generate based on timestamp
        return substr((string)time(), -5);
    }

    /**
     * Diagnose shipping configuration issues
     */
    public function diagnoseShippingConfiguration(): array
    {
        $diagnosis = [];
        $store = $this->storeManager->getStore();
        
        // Check shipping origin
        $originCountry = $this->scopeConfig->getValue(
            'shipping/origin/country_id',
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        
        $diagnosis['shipping_origin'] = [
            'country' => $originCountry,
            'configured' => !empty($originCountry)
        ];
        
        // Check active carriers
        $carriers = ['flatrate', 'freeshipping', 'tablerate', 'ups', 'dhl', 'fedex'];
        $activeCarriers = [];
        
        foreach ($carriers as $carrier) {
            $isActive = $this->scopeConfig->getValue(
                'carriers/' . $carrier . '/active',
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
            
            if ($isActive) {
                $activeCarriers[$carrier] = [
                    'title' => $this->scopeConfig->getValue(
                        'carriers/' . $carrier . '/title',
                        ScopeInterface::SCOPE_STORE,
                        $store->getId()
                    ),
                    'active' => true
                ];
                
                if ($carrier === 'flatrate') {
                    $activeCarriers[$carrier]['price'] = $this->scopeConfig->getValue(
                        'carriers/flatrate/price',
                        ScopeInterface::SCOPE_STORE,
                        $store->getId()
                    );
                }
            }
        }
        
        $diagnosis['active_carriers'] = $activeCarriers;
        $diagnosis['has_active_carriers'] = !empty($activeCarriers);
        
        return $diagnosis;
    }

    /**
     * Get country field type
     */
    public function getCountryFieldType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_COUNTRY_FIELD_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'visible';
    }

    /**
     * Check if country field should be hidden
     */
    public function isCountryFieldHidden(?int $storeId = null): bool
    {
        return $this->getCountryFieldType($storeId) === 'hidden';
    }

    /**
     * Get default country
     */
    public function getDefaultCountry(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'EG';
    }

    /**
     * Get region field type
     */
    public function getRegionFieldType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_REGION_FIELD_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'visible';
    }

    /**
     * Check if region field should be hidden
     */
    public function isRegionFieldHidden(?int $storeId = null): bool
    {
        return $this->getRegionFieldType($storeId) === 'hidden';
    }

    /**
     * Get custom CSS for quick order form
     */
    public function getCustomCss(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CUSTOM_CSS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get free shipping threshold
     */
    public function getFreeShippingThreshold(?int $storeId = null): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_FREE_SHIPPING_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }


    /**
     * Get calculated shipping cost with free shipping rules
     */
    public function getCalculatedShippingCost(float $subtotal, float $baseShippingCost, ?int $storeId = null): float
    {
        if ($this->shouldApplyFreeShipping($subtotal, $storeId)) {
            return 0.0;
        }
        
        return $baseShippingCost;
    }
}