<?php
/**
 * MagoArab_EasYorder Order Create Controller - FINAL COMPLETE SOLUTION
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Controller\Order;

use MagoArab\EasYorder\Api\QuickOrderServiceInterface;
use MagoArab\EasYorder\Api\Data\QuickOrderDataInterfaceFactory;
use MagoArab\EasYorder\Helper\Data as HelperData;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Create implements HttpPostActionInterface
{
    private $request;
    private $jsonFactory;
    private $quickOrderService;
    private $quickOrderDataFactory;
    private $helperData;
    private $formKeyValidator;
    private $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        QuickOrderServiceInterface $quickOrderService,
        QuickOrderDataInterfaceFactory $quickOrderDataFactory,
        HelperData $helperData,
        Validator $formKeyValidator,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->quickOrderService = $quickOrderService;
        $this->quickOrderDataFactory = $quickOrderDataFactory;
        $this->helperData = $helperData;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            // Check if module is enabled
            if (!$this->helperData->isEnabled()) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Quick order is not enabled.')
                ]);
            }

            // Validate form key
            if (!$this->formKeyValidator->validate($this->request)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Invalid form key.')
                ]);
            }

            // Validate required fields
            $validation = $this->validateRequest();
            if (!$validation['valid']) {
                return $result->setData([
                    'success' => false,
                    'message' => $validation['message']
                ]);
            }

            // Create order data object
            $orderData = $this->quickOrderDataFactory->create();
            $orderData->setProductId((int)$this->request->getParam('product_id'));
            $orderData->setQty((int)$this->request->getParam('qty', 1));
            $orderData->setCustomerName(trim($this->request->getParam('customer_name')));
            $orderData->setCustomerPhone(trim($this->request->getParam('customer_phone')));
            $orderData->setCustomerEmail(trim($this->request->getParam('customer_email')));
            
            // Handle street address (array)
            $street = $this->request->getParam('street', []);
            if (is_array($street)) {
                $address = implode(', ', array_filter($street));
            } else {
                $address = trim($street);
            }
            $orderData->setAddress($address);
            
            $orderData->setCity(trim($this->request->getParam('city')));
            $orderData->setCountryId(trim($this->request->getParam('country_id')));
            
            // Handle region data
            $regionId = $this->request->getParam('region_id');
            $regionText = $this->request->getParam('region');
            if ($regionId) {
                $orderData->setRegion($regionText ?: $regionId);
            } else {
                $orderData->setRegion($regionText);
            }
            
            $orderData->setPostcode(trim($this->request->getParam('postcode')));
            $orderData->setShippingMethod(trim($this->request->getParam('shipping_method')));
            $orderData->setPaymentMethod(trim($this->request->getParam('payment_method')));

            // Handle configurable product attributes
            $superAttribute = $this->request->getParam('super_attribute');
            if ($superAttribute && is_array($superAttribute)) {
                $orderData->setSuperAttribute($superAttribute);
            }

            // Log order attempt
            $this->logger->info('EasYorder: Order creation attempt', [
                'product_id' => $orderData->getProductId(),
                'shipping_method' => $orderData->getShippingMethod(),
                'payment_method' => $orderData->getPaymentMethod(),
                'country' => $orderData->getCountryId(),
                'qty' => $orderData->getQty(),
                'super_attribute' => $superAttribute
            ]);

            // Set selected product attributes in service before creating order
            if ($superAttribute && is_array($superAttribute)) {
                $this->quickOrderService->setSelectedProductAttributes($superAttribute);
            }

            // Create order
            $orderResult = $this->quickOrderService->createQuickOrder($orderData);

            return $result->setData($orderResult);

        } catch (LocalizedException $e) {
            $this->logger->error('Quick order creation failed: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in quick order creation: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('An unexpected error occurred. Please try again.')
            ]);
        }
    }

    /**
     * SMART VALIDATION - Works with ALL shipping extensions
     */
    private function validateRequest(): array
    {
        $requiredFields = [
            'product_id' => __('Product ID'),
            'customer_name' => __('Customer Name'),
            'customer_phone' => __('Customer Phone'),
            'city' => __('City'),
            'country_id' => __('Country'),
            'shipping_method' => __('Shipping Method'),
            'payment_method' => __('Payment Method')
        ];

        foreach ($requiredFields as $field => $label) {
            $value = $this->request->getParam($field);
            if (empty($value)) {
                return [
                    'valid' => false,
                    'message' => __('%1 is required.', $label)
                ];
            }
        }

        // Validate street address
        $street = $this->request->getParam('street', []);
        if (is_array($street)) {
            $streetLine1 = trim($street[0] ?? '');
        } else {
            $streetLine1 = trim($street);
        }
        
        if (empty($streetLine1)) {
            return [
                'valid' => false,
                'message' => __('Street address is required.')
            ];
        }

        // Validate product ID
        $productId = $this->request->getParam('product_id');
        if (!is_numeric($productId) || $productId <= 0) {
            return [
                'valid' => false,
                'message' => __('Invalid product ID.')
            ];
        }

        // Validate quantity
        $qty = $this->request->getParam('qty', 1);
        if (!is_numeric($qty) || $qty <= 0) {
            return [
                'valid' => false,
                'message' => __('Invalid quantity.')
            ];
        }

        // Validate phone number
        $phone = trim($this->request->getParam('customer_phone'));
        if (strlen($phone) < 8) {
            return [
                'valid' => false,
                'message' => __('Phone number must be at least 8 digits.')
            ];
        }

        // Validate email if provided
        $email = trim($this->request->getParam('customer_email'));
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => __('Invalid email address.')
            ];
        }

        // Validate region (either region_id or region text should be provided)
        $regionId = $this->request->getParam('region_id');
        $regionText = $this->request->getParam('region');
        if (empty($regionId) && empty($regionText)) {
            return [
                'valid' => false,
                'message' => __('Region is required.')
            ];
        }

        // SMART SHIPPING METHOD VALIDATION
        // This works with ALL shipping extensions including third-party
        $shippingMethod = trim($this->request->getParam('shipping_method'));
        if (!empty($shippingMethod) && $shippingMethod !== 'error') {
            
            $isValidShippingMethod = $this->validateShippingMethod($shippingMethod);
            
            if (!$isValidShippingMethod) {
                $this->logger->warning('Shipping method validation failed', [
                    'requested_method' => $shippingMethod,
                    'product_id' => $productId,
                    'country_id' => $this->request->getParam('country_id')
                ]);
                
                return [
                    'valid' => false,
                    'message' => __('Selected shipping method is not available. Please refresh and select again.')
                ];
            }
        }
        
        return ['valid' => true];
    }

    /**
     * UNIVERSAL SHIPPING METHOD VALIDATOR
     * Works with Core Magento + ALL Third-party Extensions
     */
    private function validateShippingMethod(string $shippingMethod): bool
    {
        try {
            // Step 1: Always allow fallback methods
            $fallbackMethods = [
                'fallback_standard',
                'flatrate_flatrate',
                'freeshipping_freeshipping',
                'tablerate_bestway'
            ];
            
            if (in_array($shippingMethod, $fallbackMethods)) {
                return true;
            }
            
            // Step 2: Extract carrier code from method
            $methodParts = explode('_', $shippingMethod);
            $carrierCode = $methodParts[0] ?? '';
            
            // Step 3: Known carrier patterns - always allow
            $knownCarriers = [
                'flatrate',
                'freeshipping', 
                'tablerate',
                'mageplaza',
                'mptablerate',    // Mageplaza Table Rate
                'aramex',
                'mylerz',
                'bosta',
                'dhl',
                'fedex',
                'ups',
                'temando',
                'webshopapps',
                'amasty',
                'mageworx'
            ];
            
            if (in_array($carrierCode, $knownCarriers)) {
                $this->logger->info('Shipping method validated via known carrier', [
                    'method' => $shippingMethod,
                    'carrier' => $carrierCode
                ]);
                return true;
            }
            
            // Step 4: Try to get actual available methods (optional check)
            try {
                $productId = (int)$this->request->getParam('product_id');
                $countryId = $this->request->getParam('country_id');
                $region = $this->request->getParam('region');
                $postcode = $this->request->getParam('postcode');
                
                $availableMethods = $this->quickOrderService->getAvailableShippingMethods(
                    $productId,
                    $countryId,
                    $region,
                    $postcode
                );
                
                $validMethods = array_column($availableMethods, 'code');
                
                if (in_array($shippingMethod, $validMethods)) {
                    return true;
                }
                
                // Check if any available method matches the carrier
                foreach ($validMethods as $method) {
                    if (strpos($method, $carrierCode . '_') === 0) {
                        $this->logger->info('Shipping method validated via carrier match', [
                            'requested' => $shippingMethod,
                            'available' => $method,
                            'carrier' => $carrierCode
                        ]);
                        return true;
                    }
                }
                
            } catch (\Exception $e) {
                // If API call fails, be lenient and allow the method
                $this->logger->warning('Shipping validation API failed, allowing method', [
                    'method' => $shippingMethod,
                    'error' => $e->getMessage()
                ]);
                return true;
            }
            
            // Step 5: Final fallback - check method format
            // If it looks like a valid shipping method format, allow it
            if (preg_match('/^[a-zA-Z0-9_]+_[a-zA-Z0-9_]+$/', $shippingMethod)) {
                $this->logger->info('Shipping method validated via format check', [
                    'method' => $shippingMethod
                ]);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('Shipping method validation error: ' . $e->getMessage());
            // On error, be lenient and allow the method
            return true;
        }
    }
}