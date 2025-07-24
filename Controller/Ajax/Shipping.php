<?php
/**
 * MagoArab_EasYorder Ajax Shipping Controller
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Controller\Ajax;

use MagoArab\EasYorder\Api\QuickOrderServiceInterface;
use MagoArab\EasYorder\Helper\Data as HelperData;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Shipping
 * 
 * Ajax controller for getting shipping methods
 */
class Shipping implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var QuickOrderServiceInterface
     */
    private $quickOrderService;

    /**
     * @var HelperData
     */
    private $helperData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param QuickOrderServiceInterface $quickOrderService
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        QuickOrderServiceInterface $quickOrderService,
        HelperData $helperData,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->quickOrderService = $quickOrderService;
        $this->helperData = $helperData;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
    }

    /**
     * Execute action to get shipping methods
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            // بداية التتبع المتقدم
            $debugInfo = [
                'request_start_time' => microtime(true),
                'memory_usage_start' => memory_get_usage(true),
                'request_id' => uniqid('shipping_', true)
            ];
            
            // Get request parameters
            $productId = (int)$this->request->getParam('product_id');
            $countryId = $this->request->getParam('country_id');
            $regionId = $this->request->getParam('region_id');
            $region = $this->request->getParam('region');
            $city = $this->request->getParam('city');
            $postcode = $this->request->getParam('postcode');
            $phone = $this->request->getParam('phone');
            $qty = (int)$this->request->getParam('qty', 1);
            
            // تسجيل تفصيلي للطلب
            $this->logger->info('=== EasyOrder Shipping Debug Start ===', [
                'request_id' => $debugInfo['request_id'],
                'timestamp' => date('Y-m-d H:i:s'),
                'request_params' => [
                    'product_id' => $productId,
                    'country_id' => $countryId,
                    'region_id' => $regionId,
                    'region' => $region,
                    'city' => $city,
                    'postcode' => $postcode,
                    'qty' => $qty,
                    'phone' => $phone ? 'provided' : 'not_provided'
                ],
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ]
            ]);
            
            // Auto-generate postcode from phone if enabled and postcode is empty
            if (empty($postcode) && !empty($phone) && $this->helperData->isAutoGeneratePostcodeEnabled()) {
                $postcode = $this->helperData->generatePostcodeFromPhone($phone);
                $this->logger->info('Postcode generated from phone', [
                    'request_id' => $debugInfo['request_id'],
                    'generated_postcode' => $postcode
                ]);
            }
            
            // Validate required parameters
            if (!$productId || !$countryId) {
                $this->logger->error('Missing required parameters', [
                    'request_id' => $debugInfo['request_id'],
                    'missing_product_id' => !$productId,
                    'missing_country_id' => !$countryId
                ]);
                
                return $result->setData([
                    'success' => false,
                    'message' => __('معاملات مطلوبة مفقودة: product_id و country_id مطلوبان'),
                    'shipping_methods' => [],
                    'debug_info' => $debugInfo
                ]);
            }
            
            // تحقق من وجود المنتج
            try {
                $product = $this->productRepository->getById($productId);
                $this->logger->info('Product validation successful', [
                    'request_id' => $debugInfo['request_id'],
                    'product_sku' => $product->getSku(),
                    'product_type' => $product->getTypeId(),
                    'product_weight' => $product->getWeight(),
                    'product_status' => $product->getStatus()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Product not found', [
                    'request_id' => $debugInfo['request_id'],
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                
                return $result->setData([
                    'success' => false,
                    'message' => __('المنتج غير موجود'),
                    'shipping_methods' => [],
                    'debug_info' => $debugInfo
                ]);
            }
            
            // Use region_id if available, otherwise use region text
            $regionParam = $regionId ?: $region;
            
            // تسجيل قبل استدعاء الخدمة
            $this->logger->info('Calling shipping service', [
                'request_id' => $debugInfo['request_id'],
                'service_params' => [
                    'product_id' => $productId,
                    'country_id' => $countryId,
                    'region_param' => $regionParam,
                    'postcode' => $postcode
                ]
            ]);
            
            // Get shipping methods using enhanced method
            $shippingMethods = $this->quickOrderService->getAvailableShippingMethods(
                $productId,
                $countryId,
                $regionParam,
                $postcode
            );
            
            // تسجيل النتائج
            $this->logger->info('Shipping service response', [
                'request_id' => $debugInfo['request_id'],
                'methods_count' => count($shippingMethods),
                'methods_details' => array_map(function($method) {
                    return [
                        'code' => $method['code'],
                        'title' => $method['title'],
                        'price' => $method['price']
                    ];
                }, $shippingMethods)
            ]);
            
            // Enhanced response format
            $debugInfo['processing_time'] = microtime(true) - $debugInfo['request_start_time'];
            $debugInfo['memory_usage_end'] = memory_get_usage(true);
            $debugInfo['memory_peak'] = memory_get_peak_usage(true);
            
            $response = [
                'success' => true,
                'shipping_methods' => $shippingMethods,
                'totals' => [
                    'methods_count' => count($shippingMethods),
                    'has_free_shipping' => $this->hasFreeShipping($shippingMethods)
                ],
                'debug_info' => $debugInfo,
                'request_id' => $debugInfo['request_id']
            ];
            
            if (empty($shippingMethods)) {
                $response['message'] = __('لا توجد طرق شحن متاحة لهذا الموقع.');
                $response['suggestions'] = [
                    __('تحقق من تفعيل طرق الشحن في إعدادات الإدارة'),
                    __('تأكد من صحة إعدادات أصل الشحن'),
                    __('تحقق من دعم البلد/المنطقة المحددة'),
                    __('تأكد من عدم وجود قيود شحن على المنتج'),
                    __('تحقق من صحة تنسيق الرمز البريدي')
                ];
                
                $this->logger->warning('No shipping methods returned', [
                    'request_id' => $debugInfo['request_id'],
                    'possible_causes' => $response['suggestions']
                ]);
            }
            
            $this->logger->info('=== EasyOrder Shipping Debug End ===', [
                'request_id' => $debugInfo['request_id'],
                'success' => true,
                'processing_time' => $debugInfo['processing_time'],
                'memory_used' => ($debugInfo['memory_usage_end'] - $debugInfo['memory_usage_start']) / 1024 / 1024 . ' MB'
            ]);
            
            return $result->setData($response);
            
        } catch (\Exception $e) {
            $this->logger->error('=== EasyOrder Shipping Error ===', [
                'request_id' => $debugInfo['request_id'] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_params' => $this->request->getParams()
            ]);
            
            return $result->setData([
                'success' => false,
                'message' => __('خطأ في تحميل طرق الشحن. يرجى المحاولة مرة أخرى.'),
                'shipping_methods' => [],
                'error_details' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'request_id' => $debugInfo['request_id'] ?? 'unknown'
                ]
            ]);
        }
    }
    
    /**
     * Check if any shipping method is free
     */
    private function hasFreeShipping(array $methods): bool
    {
        foreach ($methods as $method) {
            if ((float)$method['price'] === 0.0) {
                return true;
            }
        }
        return false;
    }
}