<?php
/**
 * MagoArab_EasYorder Enhanced Quick Order Service
 * Supports third-party extensions and catalog rules
 */
declare(strict_types=1);
namespace MagoArab\EasYorder\Model;
use MagoArab\EasYorder\Api\QuickOrderServiceInterface;
use MagoArab\EasYorder\Api\Data\QuickOrderDataInterface;
use MagoArab\EasYorder\Helper\Data as HelperData;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\DataObject;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\CatalogRule\Model\RuleFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\SalesRule\Model\RuleFactory as CartRuleFactory;
use Magento\SalesRule\Model\Validator as CartRuleValidator;
use Magento\CatalogRule\Model\ResourceModel\Rule as CatalogRuleResource;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
class QuickOrderService implements QuickOrderServiceInterface
{
    private $productRepository;
    private $quoteFactory;
    private $quoteManagement;
    private $storeManager;
    private $customerFactory;
    private $customerRepository;
    private $orderSender;
    private $helperData;
    private $cartRepository;
    private $cartManagement;
    private $scopeConfig;
    private $logger;
    private $shippingMethodManagement;
    private $paymentMethodList;
    private $paymentConfig;
    private $shippingConfig;
    private $regionFactory;
    private $orderRepository;
    private $priceHelper;
    private $customerSession;
    private $checkoutSession;
    private $ruleFactory;
    private $dateTime;
    private $request;
    private $cartRuleFactory;
    private $cartRuleValidator;
    private $catalogRuleResource;
    private $timezone;
    /**
     * Property to store current order attributes
     */
    private $currentOrderAttributes = null;
	public function __construct(
			ProductRepositoryInterface $productRepository,
			QuoteFactory $quoteFactory,
			QuoteManagement $quoteManagement,
			StoreManagerInterface $storeManager,
			CustomerFactory $customerFactory,
			CustomerRepositoryInterface $customerRepository,
			OrderSender $orderSender,
			HelperData $helperData,
			CartRepositoryInterface $cartRepository,
			CartManagementInterface $cartManagement,
			ScopeConfigInterface $scopeConfig,
			LoggerInterface $logger,
			ShippingMethodManagementInterface $shippingMethodManagement,
			PaymentMethodListInterface $paymentMethodList,
			PaymentConfig $paymentConfig,
			ShippingConfig $shippingConfig,
			RegionFactory $regionFactory,
			OrderRepositoryInterface $orderRepository,
			PriceHelper $priceHelper,
			CustomerSession $customerSession,
			CheckoutSession $checkoutSession,
			RuleFactory $ruleFactory,
			DateTime $dateTime,
			RequestInterface $request,
			CartRuleFactory $cartRuleFactory,
        CartRuleValidator $cartRuleValidator,
        CatalogRuleResource $catalogRuleResource,
        TimezoneInterface $timezone
    ) {
        $this->productRepository = $productRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderSender = $orderSender;
        $this->helperData = $helperData;
        $this->cartRepository = $cartRepository;
        $this->cartManagement = $cartManagement;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->paymentMethodList = $paymentMethodList;
        $this->paymentConfig = $paymentConfig;
        $this->shippingConfig = $shippingConfig;
        $this->regionFactory = $regionFactory;
        $this->orderRepository = $orderRepository;
        $this->priceHelper = $priceHelper;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->ruleFactory = $ruleFactory;
        $this->dateTime = $dateTime;
		$this->request = $request;
        $this->cartRuleFactory = $cartRuleFactory;
        $this->cartRuleValidator = $cartRuleValidator;
        $this->catalogRuleResource = $catalogRuleResource;
        $this->timezone = $timezone;
    }
    public function getAvailableShippingMethods(int $productId, string $countryId, ?string $region = null, ?string $postcode = null): array
    {
        $requestId = uniqid('service_', true);
        try {
            $this->logger->info('=== Enhanced QuickOrderService: Starting shipping calculation ===', [
                'request_id' => $requestId,
                'product_id' => $productId,
                'country_id' => $countryId,
                'region' => $region,
                'postcode' => $postcode
            ]);
            // Step 1: Create realistic quote like normal checkout with location context
            $quote = $this->createRealisticQuoteWithProduct($productId, $countryId, $region, $postcode);
            // Step 2: Use OFFICIAL Magento Shipping Method Management API
            $shippingMethods = $this->collectShippingMethodsUsingOfficialAPI($quote, $requestId);
            // Step 3: Apply admin filtering (keeps third-party compatibility)
            $filteredMethods = $this->helperData->filterShippingMethods($shippingMethods);
            $this->logger->info('=== Enhanced QuickOrderService: Shipping calculation completed ===', [
                'request_id' => $requestId,
                'original_methods_count' => count($shippingMethods),
                'filtered_methods_count' => count($filteredMethods),
                'final_methods' => array_column($filteredMethods, 'code')
            ]);
            return $filteredMethods;
        } catch (\Exception $e) {
            $this->logger->error('=== Enhanced QuickOrderService: Error in shipping calculation ===', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    /**
     * Create realistic quote that mimics normal checkout behavior with FULL rules application
     */
    private function createRealisticQuoteWithProduct(int $productId, string $countryId, ?string $region = null, ?string $postcode = null, int $qty = 1)
{
    try {
        $product = $this->productRepository->getById($productId);
        $store = $this->storeManager->getStore();
        // Create quote exactly like checkout
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId($store->getId());
        $quote->setCurrency();
        // CRITICAL: Set customer context for ALL catalog rules
        $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerEmail('guest@example.com');
        // Set realistic addresses EARLY for cart rules that depend on location
        $this->setRealisticShippingAddress($quote, $countryId, $region, $postcode);
        $this->setRealisticBillingAddress($quote, $countryId, $region, $postcode);
        // Handle product variants properly with attributes - SIMPLIFIED APPROACH
        if ($product->getTypeId() === 'configurable') {
            $selectedAttributes = $this->getSelectedProductAttributes();
            if ($selectedAttributes && !empty($selectedAttributes)) {
                // User has selected specific attributes
                $simpleProduct = $product->getTypeInstance()->getProductByAttributes($selectedAttributes, $product);
                if ($simpleProduct) {
                    // CRITICAL: Add with correct quantity from the start
                    $request = new DataObject([
                        'qty' => $qty,
                        'product' => $simpleProduct->getId()
                    ]);
                    $quote->addProduct($simpleProduct, $request);
                    $this->logger->info('Added simple product directly (user selected)', [
                        'parent_id' => $productId,
                        'simple_id' => $simpleProduct->getId(),
                        'qty' => $qty,
                        'selected_attributes' => $selectedAttributes
                    ]);
                } else {
                    throw new LocalizedException(__('Selected product configuration is not available'));
                }
            } else {
                // No attributes selected, get first available simple product
                $simpleProduct = $this->getFirstAvailableSimpleProduct($product);
                if ($simpleProduct) {
                    // Add with correct quantity
                    $request = new DataObject([
                        'qty' => $qty,
                        'product' => $simpleProduct->getId()
                    ]);
                    $quote->addProduct($simpleProduct, $request);
                    $this->logger->info('Added simple product directly (auto selected)', [
                        'parent_id' => $productId,
                        'simple_id' => $simpleProduct->getId(),
                        'qty' => $qty
                    ]);
                } else {
                    throw new LocalizedException(__('No available product variants found'));
                }
            }
        } else {
            // For simple products
            $request = new DataObject([
                'qty' => $qty,
                'product' => $product->getId()
            ]);
            $quote->addProduct($product, $request);
            $this->logger->info('Added simple product', [
                'product_id' => $productId,
                'qty' => $qty
            ]);
        }
        // ENHANCED: Multiple totals collection for COMPLETE rules application
        // Step 1: Initial totals collection
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // Step 2: Force catalog rules application
        foreach ($quote->getAllItems() as $item) {
            $item->getProduct()->setCustomerGroupId($quote->getCustomerGroupId());
            $item->calcRowTotal();
        }
        // Step 3: Reload and recalculate for cart rules
        $quote = $this->cartRepository->get($quote->getId());
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // Step 4: Final calculation to ensure ALL rules are applied
        $quote = $this->cartRepository->get($quote->getId());
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->logger->info('Enhanced quote created with FULL rules application', [
            'quote_id' => $quote->getId(),
            'total_items_count' => count($quote->getAllItems()),
            'visible_items_count' => count($quote->getAllVisibleItems()),
            'subtotal' => $quote->getSubtotal(),
            'subtotal_with_discount' => $quote->getSubtotalWithDiscount(),
            'grand_total' => $quote->getGrandTotal(),
            'applied_rule_ids' => $quote->getAppliedRuleIds(),
            'items_details' => array_map(function($item) {
                return [
                    'item_id' => $item->getId(),
                    'parent_item_id' => $item->getParentItemId(),
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty' => $item->getQty(),
                    'price' => $item->getPrice(),
                    'row_total' => $item->getRowTotal(),
                    'product_type' => $item->getProductType(),
                    'is_virtual' => $item->getIsVirtual()
                ];
            }, $quote->getAllVisibleItems()) // Use getAllVisibleItems() instead of getAllItems()
        ]);
        return $quote;
    } catch (\Exception $e) {
        $this->logger->error('Failed to create enhanced quote with rules', [
            'product_id' => $productId,
            'qty' => $qty,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw new LocalizedException(__('Unable to create quote: %1', $e->getMessage()));
    }
}
/**
 * Get selected product attributes from current request/session
 * This will be called during order creation
 */
private function getSelectedProductAttributes(): ?array
{
    try {
        // Check if we're in order creation context and have stored attributes
        if (isset($this->currentOrderAttributes)) {
            return $this->currentOrderAttributes;
        }
        return null;
    } catch (\Exception $e) {
        $this->logger->warning('Could not get selected product attributes: ' . $e->getMessage());
        return null;
    }
}
/**
 * Set selected product attributes for order creation
 */
public function setSelectedProductAttributes(array $attributes): void
{
    $this->currentOrderAttributes = $attributes;
}
    private function setRealisticShippingAddress($quote, string $countryId, ?string $region = null, ?string $postcode = null)
    {
        $shippingAddress = $quote->getShippingAddress();
        // Set complete address data
        $shippingAddress->setCountryId($countryId);
        $shippingAddress->setCity($region ? $region : 'Cairo');
        $shippingAddress->setStreet(['123 Main Street', 'Apt 1']);
        $shippingAddress->setFirstname('Guest');
        $shippingAddress->setLastname('Customer');
        $shippingAddress->setTelephone('01234567890');
        $shippingAddress->setEmail('guest@example.com');
        $shippingAddress->setCompany('');
        // Set region properly
        if ($region) {
            $regionId = $this->getRegionIdByName($region, $countryId);
            if ($regionId) {
                $shippingAddress->setRegionId($regionId);
                $shippingAddress->setRegion($region);
            } else {
                $shippingAddress->setRegion($region);
            }
        }
        // Set postcode
        if ($postcode) {
            $shippingAddress->setPostcode($postcode);
        } else {
            $shippingAddress->setPostcode('11511'); // Default Egyptian postcode
        }
        // Save address changes
        $shippingAddress->save();
        return $shippingAddress;
    }
    /**
     * Set realistic billing address for cart rules
     */
    private function setRealisticBillingAddress($quote, string $countryId, ?string $region = null, ?string $postcode = null)
    {
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setCountryId($countryId);
        $billingAddress->setCity($region ? $region : 'Cairo');
        $billingAddress->setStreet(['123 Main Street', 'Apt 1']);
        $billingAddress->setFirstname('Guest');
        $billingAddress->setLastname('Customer');
        $billingAddress->setTelephone('01234567890');
        $billingAddress->setEmail('guest@example.com');
        if ($region) {
            $regionId = $this->getRegionIdByName($region, $countryId);
            if ($regionId) {
                $billingAddress->setRegionId($regionId);
                $billingAddress->setRegion($region);
            } else {
                $billingAddress->setRegion($region);
            }
        }
        if ($postcode) {
            $billingAddress->setPostcode($postcode);
        } else {
            $billingAddress->setPostcode('11511');
        }
        $billingAddress->save();
        return $billingAddress;
    }
/**
 * FIXED: Enhanced shipping collection that works with ALL third-party extensions
 */
private function collectShippingMethodsUsingOfficialAPI($quote, string $requestId): array
{
    try {
        $this->logger->info('Enhanced Shipping Collection Started', [
            'request_id' => $requestId,
            'quote_id' => $quote->getId()
        ]);
        // STEP 1: Ensure proper customer context
        $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        $quote->setCustomerIsGuest(true);
        // STEP 2: Get shipping address and validate
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress->getCountryId()) {
            throw new \Exception('Shipping address missing country');
        }
        // STEP 3: CRITICAL FIX - Force proper address setup for shipping calculation
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->removeAllShippingRates();
        // Set weight if not set (required for many shipping methods)
        $totalWeight = 0;
        foreach ($quote->getAllItems() as $item) {
            $product = $item->getProduct();
            if ($product && $product->getWeight()) {
                $totalWeight += ($product->getWeight() * $item->getQty());
            }
        }
        if ($totalWeight > 0) {
            $shippingAddress->setWeight($totalWeight);
        } else {
            $shippingAddress->setWeight(1); // Default weight for calculation
        }
        // STEP 4: Force totals calculation BEFORE shipping collection
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // STEP 5: Manual shipping rates collection (more reliable than API)
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        // STEP 6: Force another totals collection
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // STEP 7: Get rates from address (most reliable method)
        $shippingRates = $shippingAddress->getAllShippingRates();
        $this->logger->info('Shipping rates collected', [
            'request_id' => $requestId,
            'rates_count' => count($shippingRates),
            'quote_subtotal' => $quote->getSubtotal(),
            'quote_weight' => $shippingAddress->getWeight()
        ]);
        $methods = [];
 foreach ($shippingRates as $rate) {
    // FIXED: Accept rates even with warnings, but skip null methods
    if ($rate->getMethod() !== null) {
        $methodCode = $rate->getCarrier() . '_' . $rate->getMethod();
        if ($rate->getErrorMessage()) {
            $this->logger->info('Shipping rate has warning but will be included', [
                'request_id' => $requestId,
                'carrier' => $rate->getCarrier(),
                'method' => $rate->getMethod(),
                'warning' => $rate->getErrorMessage(),
                'price' => $rate->getPrice()
            ]);
        }
        $methods[] = [
            'code' => $methodCode,
            'carrier_code' => $rate->getCarrier(),
            'method_code' => $rate->getMethod(),
            'carrier_title' => $rate->getCarrierTitle(),
            'title' => $rate->getMethodTitle(),
            'price' => (float)$rate->getPrice(),
            'price_formatted' => $this->formatPrice((float)$rate->getPrice())
        ];
        $this->logger->info('Valid shipping method found', [
            'request_id' => $requestId,
            'method_code' => $methodCode,
            'price' => $rate->getPrice(),
            'carrier_title' => $rate->getCarrierTitle()
        ]);
    } else {
        $this->logger->warning('Shipping rate has null method - skipped', [
            'request_id' => $requestId,
            'carrier' => $rate->getCarrier(),
            'method' => $rate->getMethod(),
            'error' => $rate->getErrorMessage()
        ]);
    }
}
        // STEP 8: If no methods found, try alternative approach
        if (empty($methods)) {
            $this->logger->warning('No shipping rates found, trying alternative collection', [
                'request_id' => $requestId
            ]);
            $methods = $this->collectShippingUsingCarrierModels($quote, $requestId);
        }
        // STEP 9: Fallback to configured carriers if still empty
        if (empty($methods)) {
            $this->logger->warning('No methods from carriers, using fallback', [
                'request_id' => $requestId
            ]);
            $methods = $this->getFallbackShippingMethods();
        }
        $this->logger->info('Final shipping methods result', [
            'request_id' => $requestId,
            'methods_count' => count($methods),
            'methods' => array_column($methods, 'code')
        ]);
        return $methods;
    } catch (\Exception $e) {
        $this->logger->error('Enhanced shipping collection failed', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Ultimate fallback
        return $this->getFallbackShippingMethods();
    }
}
/**
 * FIXED: Enhanced carrier collection that works with all Magento carriers
 */
private function collectShippingUsingCarrierModels($quote, string $requestId): array
{
    $methods = [];
    try {
        $this->logger->info('Starting alternative carrier collection', [
            'request_id' => $requestId
        ]);
        // Get all carriers from shipping config
        $allCarriers = $this->shippingConfig->getAllCarriers();
        $shippingAddress = $quote->getShippingAddress();
        foreach ($allCarriers as $carrierCode => $carrierModel) {
            try {
                // Check if carrier is active
                $isActive = $this->scopeConfig->getValue(
                    'carriers/' . $carrierCode . '/active',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                if (!$isActive) {
                    continue;
                }
                // Skip freeshipping if it has issues and continue to other carriers
                if ($carrierCode === 'freeshipping') {
                    $this->logger->info('Skipping freeshipping carrier for alternative collection', [
                        'request_id' => $requestId
                    ]);
                    continue;
                }
                $this->logger->info('Processing carrier', [
                    'request_id' => $requestId,
                    'carrier' => $carrierCode,
                    'model_class' => get_class($carrierModel)
                ]);
                // Create comprehensive shipping rate request
                $request = $this->createShippingRateRequest($quote, $shippingAddress);
                // Try to collect rates from carrier
                $result = $carrierModel->collectRates($request);
                if ($result && $result->getRates()) {
                    $rates = $result->getRates();
                    $this->logger->info('Carrier returned rates', [
                        'request_id' => $requestId,
                        'carrier' => $carrierCode,
                        'rates_count' => count($rates)
                    ]);
                    foreach ($rates as $rate) {
                        if ($rate->getMethod() !== null) {
                            $methodCode = $rate->getCarrier() . '_' . $rate->getMethod();
                            $methods[] = [
                                'code' => $methodCode,
                                'carrier_code' => $rate->getCarrier(),
                                'method_code' => $rate->getMethod(),
                                'carrier_title' => $rate->getCarrierTitle(),
                                'title' => $rate->getMethodTitle(),
                                'price' => (float)$rate->getPrice(),
                                'price_formatted' => $this->formatPrice((float)$rate->getPrice())
                            ];
                            $this->logger->info('Alternative carrier method collected', [
                                'request_id' => $requestId,
                                'carrier' => $carrierCode,
                                'method' => $methodCode,
                                'price' => $rate->getPrice()
                            ]);
                        }
                    }
                } else {
                    $this->logger->info('Carrier returned no rates', [
                        'request_id' => $requestId,
                        'carrier' => $carrierCode,
                        'result_class' => $result ? get_class($result) : 'null'
                    ]);
                    // For standard carriers, create fallback methods
                    if (in_array($carrierCode, ['flatrate', 'tablerate'])) {
                        $fallbackMethod = $this->createFallbackMethod($carrierCode);
                        if ($fallbackMethod) {
                            $methods[] = $fallbackMethod;
                            $this->logger->info('Created fallback method for carrier', [
                                'request_id' => $requestId,
                                'carrier' => $carrierCode,
                                'method' => $fallbackMethod['code']
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Carrier collection failed', [
                    'request_id' => $requestId,
                    'carrier' => $carrierCode,
                    'error' => $e->getMessage()
                ]);
                // Try to create basic method for known carriers
                if (in_array($carrierCode, ['flatrate', 'tablerate'])) {
                    $fallbackMethod = $this->createFallbackMethod($carrierCode);
                    if ($fallbackMethod) {
                        $methods[] = $fallbackMethod;
                    }
                }
                // Don't let one carrier failure stop others
                continue;
            }
        }
        $this->logger->info('Alternative carrier collection completed', [
            'request_id' => $requestId,
            'total_methods' => count($methods),
            'methods' => array_column($methods, 'code')
        ]);
    } catch (\Exception $e) {
        $this->logger->error('Alternative carrier collection failed completely', [
            'request_id' => $requestId,
            'error' => $e->getMessage()
        ]);
    }
    return $methods;
}
/**
 * Create comprehensive shipping rate request
 */
private function createShippingRateRequest($quote, $shippingAddress)
{
    // Create proper rate request object
    $request = new \Magento\Framework\DataObject();
    // Set destination data
    $request->setDestCountryId($shippingAddress->getCountryId());
    $request->setDestRegionId($shippingAddress->getRegionId());
    $request->setDestRegionCode($shippingAddress->getRegionCode());
    $request->setDestStreet($shippingAddress->getStreet());
    $request->setDestCity($shippingAddress->getCity());
    $request->setDestPostcode($shippingAddress->getPostcode());
    // Set package data
    $request->setPackageWeight($shippingAddress->getWeight() ?: 1);
    $request->setPackageValue($quote->getSubtotal());
    $request->setPackageValueWithDiscount($quote->getSubtotalWithDiscount());
    $request->setPackageQty($quote->getItemsQty());
    // Set store/website data
    $request->setStoreId($quote->getStoreId());
    $request->setWebsiteId($quote->getStore()->getWebsiteId());
    $request->setBaseCurrency($quote->getBaseCurrencyCode());
    $request->setPackageCurrency($quote->getQuoteCurrencyCode());
    $request->setLimitMethod(null);
    // Set origin data
    $request->setOrigCountry($this->scopeConfig->getValue(
        'shipping/origin/country_id',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ));
    $request->setOrigRegionId($this->scopeConfig->getValue(
        'shipping/origin/region_id',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ));
    $request->setOrigCity($this->scopeConfig->getValue(
        'shipping/origin/city',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ));
    $request->setOrigPostcode($this->scopeConfig->getValue(
        'shipping/origin/postcode',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ));
    // Add all items to request
    $items = [];
    foreach ($quote->getAllItems() as $item) {
        if (!$item->getParentItem()) {
            $items[] = new \Magento\Framework\DataObject([
                'qty' => $item->getQty(),
                'weight' => $item->getWeight() ?: 1,
                'product_id' => $item->getProductId(),
                'base_row_total' => $item->getBaseRowTotal(),
                'price' => $item->getPrice(),
                'row_total' => $item->getRowTotal(),
                'product' => $item->getProduct()
            ]);
        }
    }
    $request->setAllItems($items);
    return $request;
}
/**
 * Create fallback method for standard carriers
 */
private function createFallbackMethod(string $carrierCode): ?array
{
    $title = $this->scopeConfig->getValue(
        'carriers/' . $carrierCode . '/title',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    );
    if (!$title) {
        return null;
    }
    $price = 0;
    switch ($carrierCode) {
        case 'flatrate':
            $price = (float)$this->scopeConfig->getValue(
                'carriers/flatrate/price',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) ?: 25;
            break;
        case 'freeshipping':
            $price = 0;
            break;
        case 'tablerate':
            $price = 25; // Default for tablerate
            break;
    }
    return [
        'code' => $carrierCode . '_' . $carrierCode,
        'carrier_code' => $carrierCode,
        'method_code' => $carrierCode,
        'carrier_title' => $title,
        'title' => $title,
        'price' => $price,
        'price_formatted' => $this->formatPrice($price)
    ];
}
/**
 * Force collection of ALL active carriers
 */
private function forceCollectAllActiveCarriers(): array
{
    $methods = [];
    // List of standard Magento carriers
    $standardCarriers = [
        'flatrate' => 'Flat Rate',
        'freeshipping' => 'Free Shipping', 
        'tablerate' => 'Table Rate',
        'ups' => 'UPS',
        'usps' => 'USPS',
        'fedex' => 'FedEx',
        'dhl' => 'DHL'
    ];
    foreach ($standardCarriers as $carrierCode => $defaultTitle) {
        $isActive = $this->scopeConfig->getValue(
            'carriers/' . $carrierCode . '/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($isActive) {
            $title = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) ?: $defaultTitle;
            $price = 0;
            $methodCode = $carrierCode . '_' . $carrierCode;
            // Set appropriate prices
            switch ($carrierCode) {
                case 'flatrate':
                    $price = (float)$this->scopeConfig->getValue(
                        'carriers/flatrate/price',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ) ?: 25;
                    break;
                case 'freeshipping':
                    $price = 0;
                    $methodCode = 'freeshipping_freeshipping';
                    break;
                case 'tablerate':
                    $price = 30; // Default tablerate price
                    break;
                default:
                    $price = 35; // Default for other carriers
            }
            $methods[] = [
                'code' => $methodCode,
                'carrier_code' => $carrierCode,
                'method_code' => $carrierCode,
                'carrier_title' => $title,
                'title' => $title,
                'price' => $price,
                'price_formatted' => $this->formatPrice($price)
            ];
            $this->logger->info('Force collected carrier method', [
                'carrier' => $carrierCode,
                'method' => $methodCode,
                'price' => $price
            ]);
        }
    }
    return $methods;
}
/**
 * Get fallback shipping methods from system configuration
 */
private function getFallbackShippingMethods(): array
{
    // First try to force collect all active carriers
    $forcedMethods = $this->forceCollectAllActiveCarriers();
    if (!empty($forcedMethods)) {
        $this->logger->info('Using forced carrier methods', [
            'methods_count' => count($forcedMethods),
            'methods' => array_column($forcedMethods, 'code')
        ]);
        return $forcedMethods;
    }
    // Ultimate fallback
    return [[
        'code' => 'fallback_standard',
        'carrier_code' => 'fallback',
        'method_code' => 'standard',
        'carrier_title' => 'Standard Shipping',
        'title' => 'Standard Delivery',
        'price' => 25.0,
        'price_formatted' => $this->formatPrice(25.0)
    ]];
}
    /**
     * Fallback shipping collection if official API fails
     */
    private function fallbackShippingCollection($quote, string $requestId): array
    {
        try {
            $this->logger->info('Using fallback shipping collection', ['request_id' => $requestId]);
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->collectShippingRates();
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $shippingRates = $shippingAddress->getAllShippingRates();
            $methods = [];
            foreach ($shippingRates as $rate) {
                if (!$rate->getErrorMessage()) {
                    $methods[] = [
                        'code' => $rate->getCarrier() . '_' . $rate->getMethod(),
                        'carrier_code' => $rate->getCarrier(),
                        'method_code' => $rate->getMethod(),
                        'carrier_title' => $rate->getCarrierTitle(),
                        'title' => $rate->getMethodTitle(),
                        'price' => (float)$rate->getPrice(),
                        'price_formatted' => $this->formatPrice((float)$rate->getPrice())
                    ];
                }
            }
            return $methods;
        } catch (\Exception $e) {
            $this->logger->error('Fallback shipping collection failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    /**
     * Get first available simple product for configurable
     */
    private function getFirstAvailableSimpleProduct($configurableProduct)
    {
        try {
            $childProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);
            foreach ($childProducts as $childProduct) {
                if ($childProduct->isSalable() && $childProduct->getStatus() == 1) {
                    return $this->productRepository->getById($childProduct->getId());
                }
            }
            if (!empty($childProducts)) {
                return $this->productRepository->getById($childProducts[0]->getId());
            }
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting simple product variant', [
                'configurable_id' => $configurableProduct->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    /**
     * Get default attributes for simple product
     */
    private function getDefaultAttributesForSimpleProduct($configurableProduct, $simpleProduct): array
    {
        $attributes = [];
        $configurableAttributes = $configurableProduct->getTypeInstance()->getConfigurableAttributes($configurableProduct);
        foreach ($configurableAttributes as $attribute) {
            $attributeCode = $attribute->getProductAttribute()->getAttributeCode();
            $attributeId = $attribute->getAttributeId();
            $value = $simpleProduct->getData($attributeCode);
            if ($value) {
                $attributes[$attributeId] = $value;
            }
        }
        $this->logger->info('Generated default attributes for simple product', [
            'configurable_id' => $configurableProduct->getId(),
            'simple_id' => $simpleProduct->getId(),
            'attributes' => $attributes
        ]);
        return $attributes;
    }
   public function getAvailablePaymentMethods(): array
{
    try {
        $store = $this->storeManager->getStore();
        // Use OFFICIAL Payment Method List API
        $paymentMethods = $this->paymentMethodList->getActiveList($store->getId());
        $methods = [];
        foreach ($paymentMethods as $method) {
            $methodCode = $method->getCode();
            $title = $method->getTitle() ?: $this->getPaymentMethodDefaultTitle($methodCode);
            $methods[] = [
                'code' => $methodCode,
                'title' => $title
            ];
        }
        // Apply admin filtering
        $methods = $this->helperData->filterPaymentMethods($methods);
        $this->logger->info('Enhanced payment methods retrieved', [
            'count' => count($methods),
            'methods' => array_column($methods, 'code'),
            'store_id' => $store->getId()
        ]);
        return $methods;
    } catch (\Exception $e) {
        $this->logger->error('Error getting enhanced payment methods: ' . $e->getMessage());
        return $this->getFallbackPaymentMethods();
    }
}
    private function getFallbackPaymentMethods(): array
    {
        try {
            $store = $this->storeManager->getStore();
            $activePayments = $this->paymentConfig->getActiveMethods();
            $methods = [];
            foreach ($activePayments as $code => $config) {
                $isActive = $this->scopeConfig->getValue(
                    'payment/' . $code . '/active',
                    ScopeInterface::SCOPE_STORE,
                    $store->getId()
                );
                if ($isActive) {
                    $title = $this->scopeConfig->getValue(
                        'payment/' . $code . '/title',
                        ScopeInterface::SCOPE_STORE,
                        $store->getId()
                    ) ?: $this->getPaymentMethodDefaultTitle($code);
                    $methods[] = [
                        'code' => $code,
                        'title' => $title
                    ];
                }
            }
            return $methods;
        } catch (\Exception $e) {
            $this->logger->error('Error getting fallback payment methods: ' . $e->getMessage());
            return [];
        }
    }
    private function getPaymentMethodDefaultTitle(string $methodCode): string
    {
        $titles = [
            'checkmo' => 'Check / Money order',
            'banktransfer' => 'Bank Transfer Payment',
            'cashondelivery' => 'Cash On Delivery',
            'free' => 'No Payment Information Required',
            'purchaseorder' => 'Purchase Order'
        ];
        return $titles[$methodCode] ?? ucfirst(str_replace('_', ' ', $methodCode));
    }
public function createQuickOrder(QuickOrderDataInterface $orderData): array
{
    // Get selected product attributes from order data instead of request
    $superAttribute = $orderData->getSuperAttribute();
    if ($superAttribute && is_array($superAttribute)) {
        $this->setSelectedProductAttributes($superAttribute);
    }
    try {
        $this->logger->info('=== Enhanced Order Creation Started ===', [
            'product_id' => $orderData->getProductId(),
            'shipping_method' => $orderData->getShippingMethod(),
            'payment_method' => $orderData->getPaymentMethod(),
            'country' => $orderData->getCountryId(),
            'qty' => $orderData->getQty(),
            'super_attribute' => $superAttribute
        ]);
        // STEP 1: Create quote with shipping calculation FIRST
        $quote = $this->createRealisticQuoteWithProduct(
            $orderData->getProductId(),
            $orderData->getCountryId(),
            $orderData->getRegion(),
            $orderData->getPostcode()
        );
        // STEP 2: Set customer info early
        $this->setCustomerInformation($quote, $orderData);
        // STEP 3: Set addresses BEFORE shipping calculation
        $this->setBillingAddress($quote, $orderData);
        $this->setShippingAddressEarly($quote, $orderData);
        // STEP 4: Update quantity if different from 1
        if ($orderData->getQty() > 1) {
            $this->updateQuoteItemQuantity($quote, $orderData->getQty());
        }
        // STEP 5: Get FRESH shipping methods for this specific quote
        $availableShippingMethods = $this->getQuoteShippingMethods($quote);
        $this->logger->info('Fresh shipping methods for order', [
            'methods_count' => count($availableShippingMethods),
            'methods' => array_column($availableShippingMethods, 'code'),
            'requested_method' => $orderData->getShippingMethod()
        ]);
        // STEP 6: Validate and set shipping method
        $validShippingMethod = $this->validateAndSetShippingMethod($quote, $orderData, $availableShippingMethods);
        // STEP 7: Set payment method
        $this->setPaymentMethod($quote, $orderData);
        // STEP 8: Final totals collection with enhanced free shipping check
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        // Enhanced free shipping handling
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress) {
            $subtotal = $quote->getSubtotal();
            $freeShippingFlag = $shippingAddress->getFreeShipping();
            $shouldApplyFreeShipping = $this->helperData->shouldApplyFreeShipping($subtotal);
            // Apply free shipping if either flag is set or threshold is met
            if ($freeShippingFlag || $shouldApplyFreeShipping) {
                $shippingAddress->setShippingAmount(0);
                $shippingAddress->setBaseShippingAmount(0);
                $shippingAddress->setFreeShipping(true);
                // Force recalculation with free shipping
                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
                $this->logger->info('Free shipping applied during order placement', [
                    'subtotal' => $subtotal,
                    'free_shipping_flag' => $freeShippingFlag,
                    'threshold_check' => $shouldApplyFreeShipping,
                    'final_shipping_amount' => $shippingAddress->getShippingAmount()
                ]);
            }
        }
        $this->cartRepository->save($quote);
        // STEP 9: Validate quote is ready for order
        $this->validateQuoteForOrder($quote);
        // STEP 10: Place order using official API
        $orderId = $this->cartManagement->placeOrder($quote->getId());
        $order = $this->orderRepository->get($orderId);
        $this->ensureOrderVisibility($order);
        // STEP 10.5: Apply custom order status/state if configured
        $this->applyCustomOrderStatus($order);
        // STEP 11: Send email if enabled
        $this->sendOrderNotification($order);
        // Get product details for success message
        $productDetails = $this->getOrderProductDetails($order);
        $this->logger->info('Enhanced order created successfully', [
            'order_id' => $orderId,
            'increment_id' => $order->getIncrementId(),
            'grand_total' => $order->getGrandTotal(),
            'shipping_method' => $order->getShippingMethod(),
            'shipping_description' => $order->getShippingDescription(),
            'product_details' => $productDetails
        ]);
        return [
            'success' => true,
            'order_id' => $orderId,
            'increment_id' => $order->getIncrementId(),
            'message' => $this->helperData->getSuccessMessage(),
            'product_details' => $productDetails,
            'order_total' => $this->formatPrice($order->getGrandTotal()),
            'redirect_url' => $this->getOrderSuccessUrl($order)
        ];
    } catch (\Exception $e) {
        $this->logger->error('Enhanced order creation failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'order_data' => [
                'product_id' => $orderData->getProductId(),
                'shipping_method' => $orderData->getShippingMethod(),
                'payment_method' => $orderData->getPaymentMethod()
            ]
        ]);
        throw new LocalizedException(__('Unable to create order: %1', $e->getMessage()));
    }
}
/**
 * Set shipping address early without method calculation
 */
private function setShippingAddressEarly($quote, QuickOrderDataInterface $orderData): void
{
    $shippingAddress = $quote->getShippingAddress();
    $this->setAddressData($shippingAddress, $orderData, $quote->getCustomerEmail());
    // Set weight for shipping calculation
    $totalWeight = 0;
    foreach ($quote->getAllItems() as $item) {
        $product = $item->getProduct();
        if ($product && $product->getWeight()) {
            $totalWeight += ($product->getWeight() * $item->getQty());
        }
    }
    $shippingAddress->setWeight($totalWeight > 0 ? $totalWeight : 1);
    // Save address data
    $quote->collectTotals();
    $this->cartRepository->save($quote);
}
/**
 * Get shipping methods for specific quote
 */
private function getQuoteShippingMethods($quote): array
{
    $shippingAddress = $quote->getShippingAddress();
    // Force shipping rates collection
    $shippingAddress->setCollectShippingRates(true);
    $shippingAddress->removeAllShippingRates();
    $shippingAddress->collectShippingRates();
    $quote->collectTotals();
    $this->cartRepository->save($quote);
    $shippingRates = $shippingAddress->getAllShippingRates();
    $methods = [];
    foreach ($shippingRates as $rate) {
        if ($rate->getMethod() !== null) {
            $methodCode = $rate->getCarrier() . '_' . $rate->getMethod();
            $methods[] = [
                'code' => $methodCode,
                'carrier_code' => $rate->getCarrier(),
                'method_code' => $rate->getMethod(),
                'carrier_title' => $rate->getCarrierTitle(),
                'title' => $rate->getMethodTitle(),
                'price' => (float)$rate->getPrice(),
                'rate_object' => $rate // Keep reference to original rate
            ];
        }
    }
    return $methods;
}
/**
 * Validate and set shipping method on quote
 */
private function validateAndSetShippingMethod($quote, QuickOrderDataInterface $orderData, array $availableShippingMethods): string
{
    $requestedMethod = $orderData->getShippingMethod();
    $shippingAddress = $quote->getShippingAddress();
    $this->logger->info('Validating shipping method', [
        'requested_method' => $requestedMethod,
        'available_methods' => array_column($availableShippingMethods, 'code')
    ]);
    // Find exact match
// Find exact match
foreach ($availableShippingMethods as $method) {
    if ($method['code'] === $requestedMethod) {
        $shippingAddress->setShippingMethod($method['code']);
        $shippingAddress->setShippingDescription($method['carrier_title'] . ' - ' . $method['title']);
        // Check for free shipping conditions
        $subtotal = $quote->getSubtotal();
        $freeShippingThreshold = $this->dataHelper->getFreeShippingThreshold();
        $shouldApplyFreeShipping = $this->dataHelper->shouldApplyFreeShipping($subtotal);
        if ($shouldApplyFreeShipping || $method['price'] == 0 || $subtotal >= $freeShippingThreshold) {
            $shippingAddress->setShippingAmount(0);
            $shippingAddress->setBaseShippingAmount(0);
            $shippingAddress->setFreeShipping(true);
            $this->logger->info('Free shipping applied in exact match', [
                'method' => $method['code'],
                'subtotal' => $subtotal,
                'threshold' => $freeShippingThreshold,
                'original_price' => $method['price']
            ]);
        }
        $this->logger->info('Exact shipping method match found', [
            'method' => $method['code'],
            'price' => $method['price'],
            'free_shipping' => $shippingAddress->getFreeShipping()
        ]);
        return $method['code'];
    }
}
// Find carrier match
$requestedCarrier = explode('_', $requestedMethod)[0];
foreach ($availableShippingMethods as $method) {
    if ($method['carrier_code'] === $requestedCarrier) {
        $shippingAddress->setShippingMethod($method['code']);
        $shippingAddress->setShippingDescription($method['carrier_title'] . ' - ' . $method['title']);
        // Check for free shipping conditions
        $subtotal = $quote->getSubtotal();
        $freeShippingThreshold = $this->dataHelper->getFreeShippingThreshold();
        $shouldApplyFreeShipping = $this->dataHelper->shouldApplyFreeShipping($subtotal);
        if ($shouldApplyFreeShipping || $method['price'] == 0 || $subtotal >= $freeShippingThreshold) {
            $shippingAddress->setShippingAmount(0);
            $shippingAddress->setBaseShippingAmount(0);
            $shippingAddress->setFreeShipping(true);
            $this->logger->info('Free shipping applied in carrier match', [
                'method' => $method['code'],
                'subtotal' => $subtotal,
                'threshold' => $freeShippingThreshold,
                'original_price' => $method['price']
            ]);
        }
        $this->logger->info('Carrier match found', [
            'requested' => $requestedMethod,
            'used' => $method['code'],
            'free_shipping' => $shippingAddress->getFreeShipping()
        ]);
        return $method['code'];
    }
}
// Use first available method
if (!empty($availableShippingMethods)) {
    $firstMethod = $availableShippingMethods[0];
    $shippingAddress->setShippingMethod($firstMethod['code']);
    $shippingAddress->setShippingDescription($firstMethod['carrier_title'] . ' - ' . $firstMethod['title']);
    // Check for free shipping conditions
    $subtotal = $quote->getSubtotal();
    $freeShippingThreshold = $this->dataHelper->getFreeShippingThreshold();
    $shouldApplyFreeShipping = $this->dataHelper->shouldApplyFreeShipping($subtotal);
    if ($shouldApplyFreeShipping || $firstMethod['price'] == 0 || $subtotal >= $freeShippingThreshold) {
        $shippingAddress->setShippingAmount(0);
        $shippingAddress->setBaseShippingAmount(0);
        $shippingAddress->setFreeShipping(true);
        $this->logger->info('Free shipping applied to first available method', [
            'method' => $firstMethod['code'],
            'subtotal' => $subtotal,
            'threshold' => $freeShippingThreshold,
            'original_price' => $firstMethod['price']
        ]);
    }
    $this->logger->info('Using first available shipping method', [
        'requested' => $requestedMethod,
        'used' => $firstMethod['code'],
        'free_shipping' => $shippingAddress->getFreeShipping()
    ]);
    return $firstMethod['code'];
}
    throw new LocalizedException(__('No valid shipping method available for this order.'));
}
/**
 * Validate quote is ready for order placement
 */
private function validateQuoteForOrder($quote): void
{
    $shippingAddress = $quote->getShippingAddress();
    $payment = $quote->getPayment();
    // Check shipping method
    if (!$shippingAddress->getShippingMethod()) {
        throw new LocalizedException(__('Shipping method is missing. Please select a shipping method and try again.'));
    }
    // Check payment method
    if (!$payment->getMethod()) {
        throw new LocalizedException(__('Payment method is missing. Please select a payment method and try again.'));
    }
    // Check quote has items
    if (!$quote->getItemsCount()) {
        throw new LocalizedException(__('Quote has no items. Please add products to continue.'));
    }
    // Check shipping address
    if (!$shippingAddress->getCountryId() || !$shippingAddress->getCity()) {
        throw new LocalizedException(__('Shipping address is incomplete. Please provide complete address.'));
    }
    $this->logger->info('Quote validation passed', [
        'quote_id' => $quote->getId(),
        'shipping_method' => $shippingAddress->getShippingMethod(),
        'payment_method' => $payment->getMethod(),
        'items_count' => $quote->getItemsCount(),
        'grand_total' => $quote->getGrandTotal()
    ]);
}	
/**
 * Ensure order is properly indexed and visible
 */
/**
 * FIXED: Ensure order is properly saved and visible in admin
 */
private function ensureOrderVisibility($order): void
{
    try {
        // Force order state and status
        if ($order->getState() === 'new' && $order->getStatus() === 'pending') {
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus('processing');
        }
        // Force order save multiple times to ensure persistence
        $this->orderRepository->save($order);
        // Clear cache
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $cacheManager = $objectManager->get(\Magento\Framework\App\Cache\Manager::class);
            $cacheManager->clean(['db_ddl', 'collections', 'eav']);
        } catch (\Exception $e) {
            $this->logger->warning('Could not clean cache: ' . $e->getMessage());
        }
        // Force manual indexing
        try {
            $connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
            // Insert into sales_order_grid manually if not exists
            $gridTable = $connection->getTableName('sales_order_grid');
            $orderTable = $connection->getTableName('sales_order');
            // Check if record exists in grid
            $exists = $connection->fetchOne(
                "SELECT entity_id FROM {$gridTable} WHERE entity_id = ?",
                [$order->getId()]
            );
            if (!$exists) {
                // Insert manually into grid table
                $orderData = $connection->fetchRow(
                    "SELECT * FROM {$orderTable} WHERE entity_id = ?",
                    [$order->getId()]
                );
                if ($orderData) {
                    $gridData = [
                        'entity_id' => $order->getId(),
                        'status' => $order->getStatus(),
                        'store_id' => $order->getStoreId(),
                        'store_name' => $order->getStoreName(),
                        'customer_id' => $order->getCustomerId(),
                        'base_grand_total' => $order->getBaseGrandTotal(),
                        'grand_total' => $order->getGrandTotal(),
                        'increment_id' => $order->getIncrementId(),
                        'base_currency_code' => $order->getBaseCurrencyCode(),
                        'order_currency_code' => $order->getOrderCurrencyCode(),
                        'shipping_name' => $order->getShippingAddress() ? $order->getShippingAddress()->getName() : '',
                        'billing_name' => $order->getBillingAddress() ? $order->getBillingAddress()->getName() : '',
                        'created_at' => $order->getCreatedAt(),
                        'updated_at' => $order->getUpdatedAt(),
                        'billing_address' => $order->getBillingAddress() ? 
                            implode(', ', $order->getBillingAddress()->getStreet()) . ', ' . 
                            $order->getBillingAddress()->getCity() : '',
                        'shipping_address' => $order->getShippingAddress() ? 
                            implode(', ', $order->getShippingAddress()->getStreet()) . ', ' . 
                            $order->getShippingAddress()->getCity() : '',
                        'shipping_information' => $order->getShippingDescription(),
                        'customer_email' => $order->getCustomerEmail(),
                        'customer_group' => $order->getCustomerGroupId(),
                        'subtotal' => $order->getSubtotal(),
                        'shipping_and_handling' => $order->getShippingAmount(),
                        'customer_name' => $order->getCustomerName(),
                        'payment_method' => $order->getPayment() ? $order->getPayment()->getMethod() : '',
                        'total_refunded' => $order->getTotalRefunded() ?: 0
                    ];
                    $connection->insert($gridTable, $gridData);
                    $this->logger->info('Order manually inserted into grid', [
                        'order_id' => $order->getId(),
                        'increment_id' => $order->getIncrementId()
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Manual grid insertion failed: ' . $e->getMessage());
        }
        // Try to run indexer via CLI command (if possible)
        try {
            $indexerRegistry = $objectManager->get(\Magento\Framework\Indexer\IndexerRegistry::class);
            $salesOrderGridIndexer = $indexerRegistry->get('sales_order_grid');
            if ($salesOrderGridIndexer && $salesOrderGridIndexer->isValid()) {
                $salesOrderGridIndexer->reindexRow($order->getId());
            }
        } catch (\Exception $e) {
            $this->logger->warning('Indexer reindex failed: ' . $e->getMessage());
        }
        // Final save
        $this->orderRepository->save($order);
        $this->logger->info('Order visibility ensured - ENHANCED', [
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'status' => $order->getStatus(),
            'state' => $order->getState(),
            'customer_email' => $order->getCustomerEmail(),
            'grand_total' => $order->getGrandTotal()
        ]);
    } catch (\Exception $e) {
        $this->logger->error('Error ensuring order visibility: ' . $e->getMessage(), [
            'order_id' => $order->getId(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
    // Rest of the methods remain the same as previous version...
    private function updateQuoteItemQuantity($quote, int $qty): void
    {
        foreach ($quote->getAllItems() as $item) {
            $item->setQty($qty);
        }
        $quote->collectTotals();
    }
    private function setCustomerInformation($quote, QuickOrderDataInterface $orderData): void
    {
        $customerEmail = $orderData->getCustomerEmail();
        if (!$customerEmail && $this->helperData->isAutoGenerateEmailEnabled()) {
            $customerEmail = $this->helperData->generateGuestEmail($orderData->getCustomerPhone());
        }
        $quote->setCustomerEmail($customerEmail);
        $quote->setCustomerFirstname($orderData->getCustomerName());
        $quote->setCustomerLastname('');
    }
    private function setBillingAddress($quote, QuickOrderDataInterface $orderData): void
    {
        $billingAddress = $quote->getBillingAddress();
        $this->setAddressData($billingAddress, $orderData, $quote->getCustomerEmail());
    }
 /**
 * FIXED: Properly set shipping address and method
 */
private function setShippingAddressAndMethod($quote, QuickOrderDataInterface $orderData): void
{
    $shippingAddress = $quote->getShippingAddress();
    $this->setAddressData($shippingAddress, $orderData, $quote->getCustomerEmail());
    // CRITICAL: Force shipping rates collection
    $shippingAddress->setCollectShippingRates(true);
    $shippingAddress->removeAllShippingRates();
    // Set weight for shipping calculation
    $totalWeight = 0;
    foreach ($quote->getAllItems() as $item) {
        $product = $item->getProduct();
        if ($product && $product->getWeight()) {
            $totalWeight += ($product->getWeight() * $item->getQty());
        }
    }
    $shippingAddress->setWeight($totalWeight > 0 ? $totalWeight : 1);
    // Collect shipping rates
    $shippingAddress->collectShippingRates();
    // Force totals calculation
    $quote->setTotalsCollectedFlag(false);
    $quote->collectTotals();
    $this->cartRepository->save($quote);
    // FIXED: Properly validate and set shipping method
    $requestedMethod = $orderData->getShippingMethod();
    $availableRates = $shippingAddress->getAllShippingRates();
    $methodFound = false;
    $this->logger->info('Setting shipping method', [
        'requested_method' => $requestedMethod,
        'available_rates_count' => count($availableRates)
    ]);
    // Check if requested method exists in available rates
    foreach ($availableRates as $rate) {
        $rateCode = $rate->getCarrier() . '_' . $rate->getMethod();
        $this->logger->info('Available rate', [
            'rate_code' => $rateCode,
            'carrier' => $rate->getCarrier(),
            'method' => $rate->getMethod(),
            'price' => $rate->getPrice()
        ]);
        if ($rateCode === $requestedMethod || 
            $rate->getCarrier() === $requestedMethod ||
            strpos($requestedMethod, $rate->getCarrier() . '_') === 0) {
            $shippingAddress->setShippingMethod($rateCode);
            $shippingAddress->setShippingDescription($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle());
            $methodFound = true;
            $this->logger->info('Shipping method set successfully', [
                'method_code' => $rateCode,
                'description' => $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(),
                'price' => $rate->getPrice()
            ]);
            break;
        }
    }
    // If method not found, try to find similar method
    if (!$methodFound) {
        $this->logger->warning('Requested shipping method not found, trying alternatives', [
            'requested_method' => $requestedMethod
        ]);
        // Extract carrier from requested method
        $carrierCode = explode('_', $requestedMethod)[0];
        foreach ($availableRates as $rate) {
            if ($rate->getCarrier() === $carrierCode) {
                $rateCode = $rate->getCarrier() . '_' . $rate->getMethod();
                $shippingAddress->setShippingMethod($rateCode);
                $shippingAddress->setShippingDescription($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle());
                $methodFound = true;
                $this->logger->info('Alternative shipping method set', [
                    'original_request' => $requestedMethod,
                    'set_method' => $rateCode
                ]);
                break;
            }
        }
    }
    // Final fallback - use first available rate
    if (!$methodFound && !empty($availableRates)) {
        $firstRate = reset($availableRates);
        $rateCode = $firstRate->getCarrier() . '_' . $firstRate->getMethod();
        $shippingAddress->setShippingMethod($rateCode);
        $shippingAddress->setShippingDescription($firstRate->getCarrierTitle() . ' - ' . $firstRate->getMethodTitle());
        $this->logger->info('Fallback shipping method set', [
            'fallback_method' => $rateCode,
            'original_request' => $requestedMethod
        ]);
        $methodFound = true;
    }
    if (!$methodFound) {
        throw new LocalizedException(__('No valid shipping method available. Please try again.'));
    }
    // Final totals collection with shipping method
    $quote->setTotalsCollectedFlag(false);
    $quote->collectTotals();
    $this->cartRepository->save($quote);
    $this->logger->info('Shipping method final verification', [
        'quote_shipping_method' => $shippingAddress->getShippingMethod(),
        'quote_shipping_amount' => $shippingAddress->getShippingAmount(),
        'quote_grand_total' => $quote->getGrandTotal()
    ]);
}
    private function setPaymentMethod($quote, QuickOrderDataInterface $orderData): void
    {
        $payment = $quote->getPayment();
        $payment->importData(['method' => $orderData->getPaymentMethod()]);
    }
    private function sendOrderNotification($order): void
    {
        if ($this->helperData->isEmailNotificationEnabled()) {
            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to send order email: ' . $e->getMessage());
            }
        }
    }
    private function getOrderSuccessUrl($order): string
    {
        return $this->storeManager->getStore()->getUrl('checkout/onepage/success', [
            '_query' => ['order_id' => $order->getId()]
        ]);
    }
private function setAddressData($address, QuickOrderDataInterface $orderData, string $customerEmail): void
{
    // Split customer name into first and last name
    $fullName = trim($orderData->getCustomerName());
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : $firstName; // Use first name if no last name
    $address->setFirstname($firstName);
    $address->setLastname($lastName); // FIXED: Always set lastname
    // Handle street address properly
    $streetAddress = $orderData->getAddress();
    if (strpos($streetAddress, ',') !== false) {
        $streetLines = array_map('trim', explode(',', $streetAddress));
    } else {
        $streetLines = [$streetAddress];
    }
    $address->setStreet($streetLines);
    $address->setCity($orderData->getCity());
    $address->setCountryId($orderData->getCountryId());
    $address->setTelephone($this->helperData->formatPhoneNumber($orderData->getCustomerPhone()));
    $address->setEmail($customerEmail);
    if ($orderData->getRegion()) {
        $regionId = $this->getRegionIdByName($orderData->getRegion(), $orderData->getCountryId());
        if ($regionId) {
            $address->setRegionId($regionId);
        }
        $address->setRegion($orderData->getRegion());
    }
    if ($orderData->getPostcode()) {
        $address->setPostcode($orderData->getPostcode());
    }
    // IMPORTANT: Ensure all required fields are set
    if (!$address->getCompany()) {
        $address->setCompany(''); // Set empty company to avoid issues
    }
}
    public function calculateShippingCost(
        int $productId,
        string $shippingMethod,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null,
        int $qty = 1
    ): float {
        try {
            // أولاً: محاولة الحصول على طرق الشحن المتاحة
            $methods = $this->getAvailableShippingMethods($productId, $countryId, $region, $postcode);
            foreach ($methods as $method) {
                if ($method['code'] === $shippingMethod) {
                    $this->logger->info('تم العثور على طريقة الشحن المطلوبة', [
                        'method_code' => $shippingMethod,
                        'price' => $method['price']
                    ]);
                    return (float)$method['price'];
                }
            }
            // ثانياً: التحقق من قواعد الشحن المجاني
            $product = $this->productRepository->getById($productId);
            $subtotal = (float)$product->getFinalPrice() * $qty;
            $freeShippingThreshold = $this->helperData->getFreeShippingThreshold();
            if ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
                $this->logger->info('تطبيق الشحن المجاني', [
                    'subtotal' => $subtotal,
                    'threshold' => $freeShippingThreshold
                ]);
                return 0.0;
            }
            // ثالثاً: استخدام السعر الافتراضي إذا كان متوفراً
            $defaultPrice = $this->helperData->getDefaultShippingPrice();
            if ($defaultPrice > 0) {
                $this->logger->info('استخدام السعر الافتراضي للشحن', [
                    'default_price' => $defaultPrice
                ]);
                return $defaultPrice;
            }
            // إذا لم تتوفر أي طريقة شحن، إرجاع 0
            $this->logger->warning('لا توجد طرق شحن متاحة', [
                'product_id' => $productId,
                'shipping_method' => $shippingMethod,
                'country_id' => $countryId
            ]);
            return 0.0;
        } catch (\Exception $e) {
            $this->logger->error('خطأ في حساب تكلفة الشحن: ' . $e->getMessage());
            return 0.0;
        }
    }
    /**
     * Calculate shipping cost for quote with specific method
     */
    public function calculateShippingCostForQuote($quote, $shippingMethodCode)
    {
        try {
            // FIXED: تحسين حساب تكلفة الشحن وتجنب التضارب
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress || $quote->isVirtual()) {
                return 0.0;
            }
            // إعادة تعيين معدلات الشحن
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->removeAllShippingRates();
            // جمع معدلات الشحن الجديدة
            $shippingAddress->collectShippingRates();
            // البحث عن الطريقة المطلوبة
            $rates = $shippingAddress->getAllShippingRates();
            foreach ($rates as $rate) {
                if ($rate->getCode() === $shippingMethodCode) {
                    $cost = $rate->getPrice();
                    $this->logger->info('Found shipping method', [
                        'method' => $shippingMethodCode,
                        'cost' => $cost,
                        'carrier' => $rate->getCarrier()
                    ]);
                    return (float)$cost;
                }
            }
            // إذا لم توجد الطريقة، استخدم القيمة الافتراضية
            $this->logger->warning('Shipping method not found, using fallback', [
                'requested_method' => $shippingMethodCode,
                'available_methods' => array_map(function($rate) {
                    return $rate->getCode();
                }, $rates)
            ]);
            return (float)$this->scopeConfig->getValue(
                'magoarab_easyorder/shipping/fallback_shipping_price',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } catch (\Exception $e) {
            $this->logger->error('Error calculating shipping cost: ' . $e->getMessage());
            return 0.0;
        }
    }
    private function getRegionIdByName(string $regionName, string $countryId): ?int
    {
        try {
            $region = $this->regionFactory->create();
            $region->loadByName($regionName, $countryId);
            return $region->getId() ? (int)$region->getId() : null;
        } catch (\Exception $e) {
            $this->logger->warning('Could not find region ID for: ' . $regionName . ' in country: ' . $countryId);
            return null;
        }
    }
    private function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }
    /**
     * الطريقة المحسنة للحساب الديناميكي - تحاكي checkout تماماً
     */
    public function calculateDynamicPricing(
        int $productId,
        int $qty,
        string $shippingMethod,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null
    ): array {
        try {
            // إنشاء quote واقعي مثل checkout
            $quote = $this->createCheckoutLikeQuote($productId, $countryId, $region, $postcode, $qty);
            // تطبيق قواعد الكتالوج أولاً
            $this->applyCatalogRules($quote);
            // ثم تطبيق قواعد السلة
            $this->applyCartRules($quote);
            // إعداد طريقة الشحن
            $this->setupShippingMethod($quote, $shippingMethod);
            // الحساب النهائي
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            // FIXED: استخراج النتائج مع حساب سعر الوحدة الصحيح
            $productPrice = 0;
            $originalPrice = 0;
            foreach ($quote->getAllItems() as $item) {
                $itemQty = (int)$item->getQty();
                if ($itemQty > 0) {
                    // سعر الوحدة = السعر الإجمالي ÷ الكمية
                    $productPrice = (float)$item->getRowTotal() / $itemQty;
                } else {
                    $productPrice = (float)$item->getPrice();
                }
                $originalPrice = (float)$item->getProduct()->getPrice();
                break;
            }
            return [
                'product_price' => $productPrice, // سعر الوحدة الواحدة
                'original_price' => $originalPrice,
                'subtotal' => (float)$quote->getSubtotal(),
                'subtotal_incl_tax' => (float)$quote->getSubtotalInclTax(),
                'shipping_cost' => (float)$quote->getShippingAddress()->getShippingAmount(),
                'discount_amount' => (float)($quote->getSubtotal() - $quote->getSubtotalWithDiscount()),
                'total' => (float)$quote->getGrandTotal(),
                'applied_rule_ids' => $quote->getAppliedRuleIds() ?: ''
            ];
        } catch (\Exception $e) {
            $this->logger->error('خطأ في الحساب الديناميكي: ' . $e->getMessage());
            return $this->getFallbackCalculation($productId, $qty, $shippingMethod, $countryId, $region, $postcode);
        }
    }
    /**
     * إنشاء quote يحاكي checkout تماماً
     */
    private function createCheckoutLikeQuote(int $productId, string $countryId, ?string $region = null, ?string $postcode = null, int $qty = 1)
    {
        $product = $this->productRepository->getById($productId);
        $store = $this->storeManager->getStore();
        // إنشاء quote مثل checkout تماماً
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId($store->getId());
        $quote->setIsActive(true);
        $quote->setIsMultiShipping(false);
        // إعداد العميل (guest)
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        // FIXED: التأكد من إضافة المنتج مرة واحدة فقط بالكمية الصحيحة
        $request = new \Magento\Framework\DataObject([
            'qty' => $qty,
            'product' => $product->getId()
        ]);
        // التحقق من عدم وجود المنتج مسبقاً
        $existingItem = $quote->getItemByProduct($product);
        if (!$existingItem) {
            $quote->addProduct($product, $request);
        } else {
            // تحديث الكمية إذا كان المنتج موجود
            $existingItem->setQty($qty);
        }
        // إعداد عناوين الفوترة والشحن
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setCountryId($countryId);
        if ($region) $billingAddress->setRegion($region);
        if ($postcode) $billingAddress->setPostcode($postcode);
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCountryId($countryId);
        if ($region) $shippingAddress->setRegion($region);
        if ($postcode) $shippingAddress->setPostcode($postcode);
        $shippingAddress->setCollectShippingRates(true);
        // حفظ الـ quote
        $this->cartRepository->save($quote);
        return $quote;
    }
    /**
     * إعداد طريقة الشحن
     */
    private function setupShippingMethod($quote, string $shippingMethod): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setShippingMethod($shippingMethod);
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
    }
    /**
     * تحسين تطبيق قواعد الأسعار مع ضمان تطبيق جميع القواعد
     */
public function calculateOrderTotalWithDynamicRules(
    int $productId,
    int $qty,
    string $shippingMethod,
    string $countryId,
    ?string $region = null,
    ?string $postcode = null,
    ?string $couponCode = null
): array {
    try {
        // CRITICAL FIX: Clear any existing session data
        $this->checkoutSession->clearQuote();
        $this->checkoutSession->clearStorage();
        $this->logger->info('Starting clean calculation', [
            'product_id' => $productId,
            'qty' => $qty,
            'shipping_method' => $shippingMethod
        ]);
        // Create ONE quote and use it throughout
        $quote = $this->createSingleQuoteForCalculation($productId, $countryId, $region, $postcode, $qty);
        if (!$quote || !$quote->getId()) {
            throw new \Exception('Failed to create quote');
        }
        // Set shipping method ONCE
        $this->setShippingMethodOnQuote($quote, $shippingMethod);
        // Apply coupon if provided
        if (!empty($couponCode)) {
            $quote->setCouponCode($couponCode);
        }
        // SINGLE totals collection with all rules
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // Extract clean results
        $result = $this->extractCalculationResults($quote);
        // Clean up
        try {
            $this->cartRepository->delete($quote);
        } catch (\Exception $e) {
            // Silent cleanup failure
        }
        return $result;
    } catch (\Exception $e) {
        $this->logger->error('Calculation failed: ' . $e->getMessage());
        return $this->getFallbackCalculation($productId, $qty, $shippingMethod, $countryId, $region, $postcode);
    }
}
    /**
     * Internal method for calculating order total with dynamic rules
     */
    private function calculateOrderTotalWithDynamicRulesInternal($quote): array
    {
        try {
            // FIXED: تحسين إدارة Quote وتجنب الـ conflicts
            // 1. حفظ الحالة الأصلية للـ quote
            $originalTotalsCollectedFlag = $quote->getTotalsCollectedFlag();
            $originalDataChanges = $quote->getDataChanges();
            // 2. إعادة تعيين flags بشكل صحيح
            $quote->setTotalsCollectedFlag(false);
            // 3. إعادة تعيين shipping rates للعناوين
            foreach ($quote->getAllAddresses() as $address) {
                $address->setCollectShippingRates(true);
                $address->removeAllShippingRates();
            }
            // 4. تطبيق قواعد الكتالوج أولاً
            $this->applyCatalogRules($quote);
            // 5. جمع المعدلات والمجاميع بالترتيب الصحيح
            $quote->collectTotals();
            // 6. تطبيق قواعد السلة بعد حساب المجاميع
            $this->applyCartRules($quote);
            // 7. إعادة حساب المجاميع النهائية
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            // 8. حساب تكلفة الشحن بشكل صحيح مع معالجة محسنة للشحن المجاني
            $shippingAddress = $quote->getShippingAddress();
            $shippingCost = 0.0;
            if (!$quote->isVirtual() && $shippingAddress) {
                $subtotal = $quote->getSubtotal();
                // التحقق من الشحن المجاني من عدة مصادر
                $freeShippingFlag = $shippingAddress->getFreeShipping();
                $shouldApplyFreeShipping = $this->helperData->shouldApplyFreeShipping($subtotal);
                // تحقق من قواعد الشحن المجاني في ماجنتو
                $freeShippingRules = false;
                if ($shippingAddress->getShippingMethod() === 'freeshipping_freeshipping') {
                    $freeShippingRules = true;
                }
                $this->logger->info('Free shipping analysis', [
                    'subtotal' => $subtotal,
                    'free_shipping_flag' => $freeShippingFlag,
                    'threshold_check' => $shouldApplyFreeShipping,
                    'free_shipping_rules' => $freeShippingRules,
                    'shipping_method' => $shippingAddress->getShippingMethod()
                ]);
                // تطبيق الشحن المجاني إذا تحقق أي شرط
                if ($freeShippingFlag || $shouldApplyFreeShipping || $freeShippingRules) {
                    $shippingCost = 0.0;
                    // فرض الشحن المجاني على العنوان
                    $shippingAddress->setShippingAmount(0);
                    $shippingAddress->setBaseShippingAmount(0);
                    $shippingAddress->setFreeShipping(true);
                    $this->logger->info('Free shipping applied in calculation', [
                        'reason' => $freeShippingFlag ? 'flag' : ($shouldApplyFreeShipping ? 'threshold' : 'rules')
                    ]);
                } else {
                    $baseShippingAmount = $shippingAddress->getBaseShippingAmount();
                    $shippingAmount = $shippingAddress->getShippingAmount();
                    // استخدام القيمة الأساسية أو العادية حسب التوفر
                    $shippingCost = $baseShippingAmount ?: $shippingAmount;
                    // التأكد من أن القيمة منطقية
                    if ($shippingCost < 0) {
                        $shippingCost = 0.0;
                    }
                }
            }
            // 9. حساب النتائج النهائية
            $subtotal = $quote->getSubtotal();
            $grandTotal = $quote->getGrandTotal();
            $discount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
            // FIXED: حساب سعر الوحدة الصحيح
            $productPrice = $subtotal;
            $items = $quote->getAllVisibleItems();
            if (!empty($items)) {
                $item = $items[0];
                $qty = (int)$item->getQty();
                if ($qty > 0) {
                    // سعر الوحدة = السعر الإجمالي ÷ الكمية
                    $productPrice = (float)$item->getRowTotal() / $qty;
                }
            }
            // 10. تسجيل النتائج للمراجعة
            $this->logger->info('Final calculation results', [
                'product_price' => $productPrice,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'quote_id' => $quote->getId()
            ]);
            return [
                'product_price' => $productPrice, // FIXED: سعر الوحدة الصحيح
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'total' => $grandTotal
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error in calculateOrderTotalWithDynamicRulesInternal: ' . $e->getMessage());
            return $this->getFallbackCalculationFromQuote($quote);
        }
    }
    /**
 * Create a single quote for calculation purposes
 */
private function createSingleQuoteForCalculation(int $productId, string $countryId, ?string $region = null, ?string $postcode = null, int $qty = 1)
{
    try {
        $product = $this->productRepository->getById($productId);
        $store = $this->storeManager->getStore();
        // Create clean quote
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId($store->getId());
        $quote->setCurrency();
        // Set customer context for rules
        $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerEmail('guest@calculation.local');
        // Add product with correct quantity - ONCE
        $this->addProductToQuote($quote, $product, $qty);
        // Set addresses for location-based rules
        $this->setCalculationAddresses($quote, $countryId, $region, $postcode);
        // Save quote
        $this->cartRepository->save($quote);
        return $quote;
    } catch (\Exception $e) {
        $this->logger->error('Failed to create calculation quote: ' . $e->getMessage());
        throw $e;
    }
}
/**
 * Add product to quote without duplication
 */
private function addProductToQuote($quote, $product, int $qty)
{
    // Handle configurable products
    if ($product->getTypeId() === 'configurable') {
        $selectedAttributes = $this->getSelectedProductAttributes();
        if ($selectedAttributes && !empty($selectedAttributes)) {
            $simpleProduct = $product->getTypeInstance()->getProductByAttributes($selectedAttributes, $product);
            if ($simpleProduct) {
                $product = $simpleProduct;
            }
        } else {
            $simpleProduct = $this->getFirstAvailableSimpleProduct($product);
            if ($simpleProduct) {
                $product = $simpleProduct;
            }
        }
    }
    // Add product with exact quantity
    $request = new \Magento\Framework\DataObject([
        'qty' => $qty,
        'product' => $product->getId()
    ]);
    $quote->addProduct($product, $request);
}
/**
 * Set shipping method on quote
 */
private function setShippingMethodOnQuote($quote, string $shippingMethod)
{
    $shippingAddress = $quote->getShippingAddress();
    if (!$quote->isVirtual() && $shippingAddress) {
        // Force shipping rates collection
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->removeAllShippingRates();
        $shippingAddress->collectShippingRates();
        // Set shipping method
        $shippingAddress->setShippingMethod($shippingMethod);
    }
}
/**
 * Set addresses for calculation
 */
private function setCalculationAddresses($quote, string $countryId, ?string $region = null, ?string $postcode = null)
{
    $addressData = [
        'country_id' => $countryId,
        'region' => $region ?: 'Cairo',
        'postcode' => $postcode ?: '11511',
        'city' => 'Cairo',
        'street' => ['123 Main St'],
        'firstname' => 'Guest',
        'lastname' => 'Customer',
        'telephone' => '01234567890',
        'email' => 'guest@calculation.local'
    ];
    // Set billing address
    $billingAddress = $quote->getBillingAddress();
    $billingAddress->addData($addressData);
    // Set shipping address
    if (!$quote->isVirtual()) {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($addressData);
    }
}
/**
     * Extract calculation results from quote
     */
    private function extractCalculationResults($quote): array
    {
        $subtotal = (float)$quote->getSubtotal();
        $grandTotal = (float)$quote->getGrandTotal();
        $shippingAmount = 0.0;
        $discountAmount = 0.0;
        // Get shipping cost
        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAmount = (float)$shippingAddress->getShippingAmount();
        }
        // Calculate discount
        $discountAmount = $subtotal - (float)$quote->getSubtotalWithDiscount();
        // FIXED: حساب سعر الوحدة الصحيح
        $productPrice = $subtotal;
        $items = $quote->getAllVisibleItems();
        if (!empty($items)) {
            $item = $items[0];
            $qty = (int)$item->getQty();
            if ($qty > 0) {
                // سعر الوحدة = السعر الإجمالي ÷ الكمية
                $productPrice = (float)$item->getRowTotal() / $qty;
            }
        }
        return [
            'product_price' => $productPrice,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingAmount,
            'discount_amount' => $discountAmount,
            'total' => $grandTotal,
            'applied_rule_ids' => $quote->getAppliedRuleIds() ?: '',
            'has_discount' => $discountAmount > 0,
            'coupon_code' => $quote->getCouponCode() ?: ''
        ];
    }
/**
 * Enhanced quote creation that prevents price doubling
 */
private function createEnhancedQuoteForCalculation(int $productId, string $countryId, ?string $region = null, ?string $postcode = null, int $qty = 1)
{
    static $quoteCache = [];
    $cacheKey = md5($productId . $countryId . $region . $postcode . $qty);
    // Return cached quote if exists and valid
    if (isset($quoteCache[$cacheKey])) {
        $cachedQuote = $quoteCache[$cacheKey];
        if ($cachedQuote && $cachedQuote->getId()) {
            return $cachedQuote;
        }
    }
    try {
        $product = $this->productRepository->getById($productId);
        $store = $this->storeManager->getStore();
        // Create fresh quote
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId($store->getId());
        $quote->setCurrency();
        // Set customer context for proper rule application
        $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerEmail('calculation@guest.local');
        // Handle product variants properly
        if ($product->getTypeId() === 'configurable') {
            $selectedAttributes = $this->getSelectedProductAttributes();
            if ($selectedAttributes && !empty($selectedAttributes)) {
                $simpleProduct = $product->getTypeInstance()->getProductByAttributes($selectedAttributes, $product);
                if ($simpleProduct) {
                    $product = $this->productRepository->getById($simpleProduct->getId());
                }
            } else {
                $firstSimple = $this->getFirstAvailableSimpleProduct($product);
                if ($firstSimple) {
                    $product = $firstSimple;
                }
            }
        }
        // Add product ONCE with correct quantity
        $request = new \Magento\Framework\DataObject([
            'qty' => $qty,
            'product' => $product->getId()
        ]);
        $quote->addProduct($product, $request);
        // Set proper addresses for location-based rules
        $this->setEnhancedAddresses($quote, $countryId, $region, $postcode);
        // Single totals collection
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // Cache the quote
        $quoteCache[$cacheKey] = $quote;
        $this->logger->info('Enhanced quote created successfully', [
            'quote_id' => $quote->getId(),
            'product_id' => $productId,
            'qty' => $qty,
            'subtotal' => $quote->getSubtotal(),
            'items_count' => count($quote->getAllVisibleItems())
        ]);
        return $quote;
    } catch (\Exception $e) {
        $this->logger->error('Enhanced quote creation failed: ' . $e->getMessage());
        throw $e;
    }
}
    /**
     * Fallback calculation from existing quote
     */
    private function getFallbackCalculationFromQuote($quote): array
    {
        try {
            $subtotal = $quote->getSubtotal();
            $grandTotal = $quote->getGrandTotal();
            $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
            // FIXED: حساب سعر الوحدة الصحيح
            $productPrice = $subtotal;
            $items = $quote->getAllVisibleItems();
            if (!empty($items)) {
                $item = $items[0];
                $qty = (int)$item->getQty();
                if ($qty > 0) {
                    $productPrice = (float)$item->getRowTotal() / $qty;
                }
            }
            return [
                'product_price' => $productPrice, // سعر الوحدة الواحدة
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingAmount ?: 0.0,
                'discount' => 0.0,
                'total' => $grandTotal
            ];
        } catch (\Exception $e) {
            $this->logger->error('Fallback calculation failed: ' . $e->getMessage());
            return [
                'product_price' => 0.0,
                'subtotal' => 0.0,
                'shipping_cost' => 0.0,
                'discount' => 0.0,
                'total' => 0.0
            ];
        }
    }
    /**
     * تطبيق قواعد الكتالوج بالطريقة الصحيحة - مثل checkout
     */
    private function applyCatalogRules($quote): void
    {
        try {
            $this->logger->info('بدء تطبيق قواعد الكتالوج بالطريقة الصحيحة', [
                'quote_id' => $quote->getId(),
                'customer_group_id' => $quote->getCustomerGroupId(),
                'website_id' => $this->storeManager->getStore()->getWebsiteId()
            ]);
            // استخدام checkout session للحصول على quote محدث
            $this->checkoutSession->setQuoteId($quote->getId());
            $sessionQuote = $this->checkoutSession->getQuote();
            foreach ($sessionQuote->getAllItems() as $item) {
                try {
                    $product = $item->getProduct();
                    if (!$product || !$product->getId()) {
                        continue;
                    }
                    $websiteId = $this->storeManager->getStore()->getWebsiteId();
                    $customerGroupId = $sessionQuote->getCustomerGroupId();
                    if (!$this->catalogRuleResource) {
                        $this->logger->warning('CatalogRuleResource not available, skipping catalog rules');
                        continue;
                    }
                    // الطريقة الصحيحة لإنشاء DateTime object
                    $currentDate = $this->timezone->date();
                    // استخدام الطريقة الصحيحة للحصول على قواعد الكتالوج
                    $rulePrice = $this->catalogRuleResource->getRulePrice(
                        $currentDate,
                        $websiteId,
                        $customerGroupId,
                        $product->getId()
                    );
                    if ($rulePrice !== false && $rulePrice !== null && $rulePrice < $product->getPrice()) {
                        $this->logger->info('تم تطبيق قاعدة كتالوج', [
                            'product_id' => $product->getId(),
                            'original_price' => $product->getPrice(),
                            'rule_price' => $rulePrice,
                            'discount_amount' => $product->getPrice() - $rulePrice
                        ]);
                        // تطبيق السعر الجديد بالطريقة الصحيحة
                        $item->setCustomPrice($rulePrice);
                        $item->setOriginalCustomPrice($rulePrice);
                        $item->getProduct()->setIsSuperMode(true);
                        // إعادة حساب إجمالي الصف
                        $item->calcRowTotal();
                    }
                } catch (\Exception $e) {
                    $this->logger->error('خطأ في تطبيق قاعدة كتالوج للمنتج: ' . $e->getMessage(), [
                        'product_id' => $item->getProduct()->getId()
                    ]);
                }
            }
            // نسخ التحديثات إلى الـ quote الأصلي
            $quote->merge($sessionQuote);
        } catch (\Exception $e) {
            $this->logger->error('خطأ عام في تطبيق قواعد الكتالوج: ' . $e->getMessage());
        }
    }
    /**
     * تطبيق قواعد السلة بالطريقة الصحيحة - مثل checkout
     */
    private function applyCartRules($quote): void
    {
        try {
            $this->logger->info('بدء تطبيق قواعد السلة بطريقة checkout', [
                'quote_id' => $quote->getId(),
                'subtotal' => $quote->getSubtotal(),
                'customer_group_id' => $quote->getCustomerGroupId(),
                'coupon_code' => $quote->getCouponCode()
            ]);
            // استخدام checkout session للحصول على quote محدث
            $this->checkoutSession->setQuoteId($quote->getId());
            $sessionQuote = $this->checkoutSession->getQuote();
            // إعادة تعيين العلامة وإعادة الحساب - الطريقة الصحيحة
            $sessionQuote->setTotalsCollectedFlag(false);
            // تطبيق قواعد السلة من خلال collectTotals - مثل checkout
            $sessionQuote->collectTotals();
            $this->cartRepository->save($sessionQuote);
            // نسخ النتائج إلى الـ quote الأصلي
            $quote->setSubtotal($sessionQuote->getSubtotal());
            $quote->setSubtotalWithDiscount($sessionQuote->getSubtotalWithDiscount());
            $quote->setGrandTotal($sessionQuote->getGrandTotal());
            $quote->setAppliedRuleIds($sessionQuote->getAppliedRuleIds());
            $this->logger->info('نتائج تطبيق قواعد السلة', [
                'quote_id' => $quote->getId(),
                'subtotal' => $quote->getSubtotal(),
                'subtotal_with_discount' => $quote->getSubtotalWithDiscount(),
                'discount_amount' => $quote->getSubtotal() - $quote->getSubtotalWithDiscount(),
                'grand_total' => $quote->getGrandTotal(),
                'applied_rule_ids' => $quote->getAppliedRuleIds()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('خطأ في تطبيق قواعد السلة: ' . $e->getMessage());
        }
    }
    /**
     * الحصول على قواعد السلة النشطة
     */
    private function getActiveCartRules($quote): array
    {
        $rules = [];
        try {
            $ruleCollection = $this->cartRuleFactory->create()->getCollection()
                ->addWebsiteFilter($quote->getStore()->getWebsiteId())
                ->addCustomerGroupFilter($quote->getCustomerGroupId())
                ->addDateFilter()
                ->addIsActiveFilter();
            foreach ($ruleCollection as $rule) {
                $rules[] = $rule;
            }
        } catch (\Exception $e) {
            $this->logger->error('خطأ في الحصول على قواعد السلة النشطة: ' . $e->getMessage());
        }
        return $rules;
    }
    /**
     * إعادة حساب طرق الشحن بعد تطبيق قواعد الأسعار
     */
    private function recalculateShippingAfterPriceRules($quote, string $requestedShippingMethod): void
    {
        $this->logger->info('إعادة حساب طرق الشحن بعد تطبيق قواعد الأسعار', [
            'quote_id' => $quote->getId(),
            'subtotal_before_shipping' => $quote->getSubtotal(),
            'requested_shipping_method' => $requestedShippingMethod
        ]);
        $shippingAddress = $quote->getShippingAddress();
        // إزالة طرق الشحن الحالية لإعادة حسابها
        $shippingAddress->removeAllShippingRates();
        $shippingAddress->setCollectShippingRates(true);
        // إعادة حساب طرق الشحن بناءً على السعر الجديد
        $shippingAddress->collectShippingRates();
        // البحث عن طريقة الشحن المطلوبة في الطرق المحدثة
        $availableRates = $shippingAddress->getAllShippingRates();
        $methodFound = false;
        foreach ($availableRates as $rate) {
            if ($rate->getCode() === $requestedShippingMethod) {
                $shippingAddress->setShippingMethod($requestedShippingMethod);
                $methodFound = true;
                $this->logger->info('تم العثور على طريقة الشحن المطلوبة بعد إعادة الحساب', [
                    'shipping_method' => $requestedShippingMethod,
                    'shipping_cost' => $rate->getPrice(),
                    'method_title' => $rate->getMethodTitle()
                ]);
                break;
            }
        }
        if (!$methodFound) {
            // إذا لم تعد طريقة الشحن متاحة، استخدم أول طريقة متاحة
            if (!empty($availableRates)) {
                $firstRate = reset($availableRates);
                $shippingAddress->setShippingMethod($firstRate->getCode());
                $this->logger->warning('طريقة الشحن المطلوبة غير متاحة، تم استخدام البديل', [
                    'requested_method' => $requestedShippingMethod,
                    'fallback_method' => $firstRate->getCode(),
                    'fallback_cost' => $firstRate->getPrice()
                ]);
            } else {
                $this->logger->error('لا توجد طرق شحن متاحة بعد إعادة الحساب');
            }
        }
        // لوج جميع طرق الشحن المتاحة للتشخيص
        $this->logger->info('طرق الشحن المتاحة بعد إعادة الحساب', [
            'available_methods' => array_map(function($rate) {
                return [
                    'code' => $rate->getCode(),
                    'method_title' => $rate->getMethodTitle(),
                    'carrier_title' => $rate->getCarrierTitle(),
                    'price' => $rate->getPrice()
                ];
            }, $availableRates)
        ]);
    }
    /**
     * Fallback calculation method
     */
    private function getFallbackCalculation(
        int $productId,
        int $qty,
        string $shippingMethod,
        string $countryId,
        ?string $region = null,
        ?string $postcode = null
    ): array {
        $product = $this->productRepository->getById($productId);
        $productPrice = (float)$product->getFinalPrice(); // سعر الوحدة الواحدة
        $subtotal = $productPrice * $qty;
        $shippingCost = $this->calculateShippingCost($productId, $shippingMethod, $countryId, $region, $postcode, $qty);
        return [
            'product_price' => $productPrice, // سعر الوحدة الواحدة
            'original_price' => $productPrice,
            'subtotal' => $subtotal,
            'subtotal_incl_tax' => $subtotal,
            'shipping_cost' => $shippingCost,
            'discount_amount' => 0,
            'total' => $subtotal + $shippingCost,
            'applied_rule_ids' => '',
            'has_discount' => false
        ];
    }
    /**
     * تطبيق كود الكوبون على الـ quote
     */
    public function applyCouponCode($quote, string $couponCode): array
    {
        try {
            $this->logger->info('تطبيق كود الكوبون', [
                'quote_id' => $quote->getId(),
                'coupon_code' => $couponCode
            ]);
            // تطبيق كود الكوبون
            $quote->setCouponCode($couponCode);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            // التحقق من صحة الكوبون
            $appliedCoupon = $quote->getCouponCode();
            $discountAmount = abs($quote->getShippingAddress()->getDiscountAmount());
            if ($appliedCoupon === $couponCode && $discountAmount > 0) {
                $this->cartRepository->save($quote);
                return [
                    'success' => true,
                    'message' => __('Coupon code applied successfully'),
                    'discount_amount' => $discountAmount,
                    'coupon_code' => $appliedCoupon,
                    'applied_rule_ids' => $quote->getAppliedRuleIds()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Invalid coupon code or no discount applied'),
                    'coupon_code' => $couponCode
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('خطأ في تطبيق كود الكوبون: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Error applying coupon code: %1', $e->getMessage())
            ];
        }
    }
    /**
     * إزالة كود الكوبون
     */
    public function removeCouponCode($quote): array
    {
        try {
            $quote->setCouponCode('');
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            return [
                'success' => true,
                'message' => __('Coupon code removed successfully')
            ];
        } catch (\Exception $e) {
            $this->logger->error('خطأ في إزالة كود الكوبون: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Error removing coupon code')
            ];
        }
    }
	/**
 * Update quantity and recalculate shipping methods and costs
 * يامان لازم تفهم ان الكمية وارد تزيد وتقل حسب ما العميل عايز بعد تحديد طريقة الشحن
 * يعني مفروض اي زيادة او نقص طرق الشحن تتحدث واتكلفة الشحن ايضا
 */
public function updateQuantityAndRecalculateShipping(
    int $quoteId,
    int $newQty,
    string $shippingMethod,
    string $countryId,
    ?string $region = null,
    ?string $postcode = null
): array {
    try {
        $this->logger->info('تحديث الكمية وإعادة حساب الشحن', [
            'quote_id' => $quoteId,
            'new_qty' => $newQty,
            'shipping_method' => $shippingMethod
        ]);
        // تحميل الـ quote
        $quote = $this->cartRepository->get($quoteId);
        // تحديث كمية المنتج
        $items = $quote->getAllVisibleItems();
        if (empty($items)) {
            throw new \Exception('No items found in quote');
        }
        $item = $items[0];
        $oldQty = $item->getQty();
        // تحديث الكمية
        $item->setQty($newQty);
        $this->logger->info('تم تحديث الكمية', [
            'item_id' => $item->getId(),
            'old_qty' => $oldQty,
            'new_qty' => $newQty,
            'product_sku' => $item->getSku()
        ]);
        // إعادة حساب الأسعار والقواعد
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // إعادة تطبيق قواعد الكتالوج والسلة
        $this->applyCatalogRules($quote);
        $this->applyCartRules($quote);
        $this->cartRepository->save($quote);
        // إعادة حساب طرق الشحن بناءً على الكمية والسعر الجديد
        $this->recalculateShippingAfterQuantityChange($quote, $shippingMethod, $countryId, $region, $postcode);
        // حساب نهائي
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        // إرجاع النتائج المحدثة
        $updatedItem = $quote->getAllVisibleItems()[0];
        $shippingAddress = $quote->getShippingAddress();
        // FIXED: حساب سعر الوحدة الصحيح
        $qty = (int)$updatedItem->getQty();
        $unitPrice = $qty > 0 ? (float)$updatedItem->getRowTotal() / $qty : (float)$updatedItem->getPrice();
        return [
            'success' => true,
            'product_price' => $unitPrice, // سعر الوحدة الواحدة
            'subtotal' => (float)$updatedItem->getRowTotal(),
            'shipping_cost' => (float)$shippingAddress->getShippingAmount(),
            'discount_amount' => abs((float)$updatedItem->getDiscountAmount()),
            'total' => (float)$quote->getGrandTotal(),
            'qty' => $qty,
            'applied_rule_ids' => $quote->getAppliedRuleIds(),
            'shipping_method' => $shippingAddress->getShippingMethod(),
            'available_shipping_methods' => $this->getUpdatedShippingMethods($quote)
        ];
    } catch (\Exception $e) {
        $this->logger->error('خطأ في تحديث الكمية وإعادة حساب الشحن: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
/**
 * إعادة حساب طرق الشحن بعد تغيير الكمية
 */
private function recalculateShippingAfterQuantityChange(
    $quote, 
    string $requestedShippingMethod, 
    string $countryId, 
    ?string $region = null, 
    ?string $postcode = null
): void {
    $this->logger->info('إعادة حساب طرق الشحن بعد تغيير الكمية', [
        'quote_id' => $quote->getId(),
        'new_subtotal' => $quote->getSubtotal(),
        'requested_shipping_method' => $requestedShippingMethod
    ]);
    $shippingAddress = $quote->getShippingAddress();
    // تحديث عنوان الشحن إذا لزم الأمر
    if ($region || $postcode) {
        $this->updateShippingAddress($shippingAddress, $countryId, $region, $postcode);
    }
    // إزالة جميع طرق الشحن الحالية
    $shippingAddress->removeAllShippingRates();
    $shippingAddress->setCollectShippingRates(true);
    // إعادة جمع طرق الشحن بناءً على الكمية والسعر الجديد
    $shippingAddress->collectShippingRates();
    // البحث عن طريقة الشحن المطلوبة
    $availableRates = $shippingAddress->getAllShippingRates();
    $methodFound = false;
    $this->logger->info('طرق الشحن المتاحة بعد تغيير الكمية', [
        'available_methods_count' => count($availableRates),
        'methods' => array_map(function($rate) {
            return [
                'code' => $rate->getCode(),
                'title' => $rate->getMethodTitle(),
                'price' => $rate->getPrice(),
                'carrier' => $rate->getCarrierTitle()
            ];
        }, $availableRates)
    ]);
    // محاولة تطبيق طريقة الشحن المطلوبة
    foreach ($availableRates as $rate) {
        if ($rate->getCode() === $requestedShippingMethod) {
            $shippingAddress->setShippingMethod($requestedShippingMethod);
            $methodFound = true;
            $this->logger->info('تم تطبيق طريقة الشحن المطلوبة بعد تغيير الكمية', [
                'shipping_method' => $requestedShippingMethod,
                'new_shipping_cost' => $rate->getPrice()
            ]);
            break;
        }
    }
    // إذا لم تعد طريقة الشحن متاحة، استخدم أول طريقة متاحة
    if (!$methodFound && !empty($availableRates)) {
        $firstRate = reset($availableRates);
        $shippingAddress->setShippingMethod($firstRate->getCode());
        $this->logger->warning('طريقة الشحن المطلوبة غير متاحة بعد تغيير الكمية، تم استخدام البديل', [
            'requested_method' => $requestedShippingMethod,
            'fallback_method' => $firstRate->getCode(),
            'fallback_cost' => $firstRate->getPrice()
        ]);
    }
}
/**
 * تحديث عنوان الشحن
 */
private function updateShippingAddress($shippingAddress, string $countryId, ?string $region = null, ?string $postcode = null): void
{
    if ($region) {
        $regionId = $this->getRegionIdByName($region, $countryId);
        if ($regionId) {
            $shippingAddress->setRegionId($regionId);
        }
        $shippingAddress->setRegion($region);
    }
    if ($postcode) {
        $shippingAddress->setPostcode($postcode);
    }
    $shippingAddress->setCountryId($countryId);
}
/**
 * الحصول على طرق الشحن المحدثة
 */
private function getUpdatedShippingMethods($quote): array
{
    $shippingAddress = $quote->getShippingAddress();
    $availableRates = $shippingAddress->getAllShippingRates();
    $methods = [];
    foreach ($availableRates as $rate) {
        $methods[] = [
            'code' => $rate->getCode(),
            'title' => $rate->getMethodTitle(),
            'carrier_title' => $rate->getCarrierTitle(),
            'price' => (float)$rate->getPrice(),
            'price_formatted' => $this->formatPrice($rate->getPrice())
        ];
    }
    return $methods;
}
/**
 * Get detailed product information for success message
 */
private function getOrderProductDetails($order): array
{
    $productDetails = [];
    foreach ($order->getAllItems() as $item) {
        $product = $item->getProduct();
        // FIXED: حساب السعر الصحيح
        $unitPrice = (float)$item->getPrice(); // سعر الوحدة الواحدة
        $totalPrice = (float)$item->getRowTotal(); // السعر الإجمالي للكمية
        $qty = (int)$item->getQtyOrdered();
        // التحقق من صحة الحسابات
        $calculatedTotal = $unitPrice * $qty;
        if (abs($calculatedTotal - $totalPrice) > 0.01) {
            $this->logger->warning('Price calculation mismatch detected', [
                'item_id' => $item->getId(),
                'unit_price' => $unitPrice,
                'qty' => $qty,
                'calculated_total' => $calculatedTotal,
                'actual_total' => $totalPrice
            ]);
        }
        $details = [
            'name' => $item->getName(),
            'sku' => $item->getSku(),
            'qty' => $qty,
            'price' => $this->formatPrice($unitPrice), // سعر الوحدة
            'row_total' => $this->formatPrice($totalPrice), // السعر الإجمالي
            'product_type' => $product->getTypeId()
        ];
        // إضافة تفاصيل المنتج المتعدد (Configurable Product)
        if ($product->getTypeId() === 'configurable') {
            $productOptions = $item->getProductOptions();
            if (isset($productOptions['attributes_info']) && is_array($productOptions['attributes_info'])) {
                $details['attributes'] = [];
                foreach ($productOptions['attributes_info'] as $attribute) {
                    $details['attributes'][] = [
                        'label' => $attribute['label'],
                        'value' => $attribute['value']
                    ];
                }
            }
        }
        // إضافة خيارات المنتج المخصصة
        if ($item->getProductOptions()) {
            $productOptions = $item->getProductOptions();
            if (isset($productOptions['options']) && is_array($productOptions['options'])) {
                $details['custom_options'] = [];
                foreach ($productOptions['options'] as $option) {
                    $details['custom_options'][] = [
                        'label' => $option['label'],
                        'value' => $option['value']
                    ];
                }
            }
        }
        $productDetails[] = $details;
    }
    return $productDetails;
}
/**
 * Apply custom order status and state if configured
 */
private function applyCustomOrderStatus($order): void
{
    try {
        $customStatus = $this->helperData->getDefaultOrderStatus();
        $customState = $this->helperData->getDefaultOrderState();
        if ($customStatus) {
            $order->setStatus($customStatus);
            $this->logger->info('Applied custom order status', [
                'order_id' => $order->getId(),
                'custom_status' => $customStatus
            ]);
        }
        if ($customState) {
            $order->setState($customState);
            $this->logger->info('Applied custom order state', [
                'order_id' => $order->getId(),
                'custom_state' => $customState
            ]);
        }
        if ($customStatus || $customState) {
            $this->orderRepository->save($order);
        }
    } catch (\Exception $e) {
        $this->logger->warning('Could not apply custom order status/state: ' . $e->getMessage());
    }
}
}