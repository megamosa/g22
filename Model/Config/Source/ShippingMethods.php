<?php
/**
 * MagoArab_EasYorder Enhanced Shipping Methods Source Model
 * Detects ALL shipping methods including third-party extensions
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ShippingMethods implements OptionSourceInterface
{
    private $shippingConfig;
    private $scopeConfig;
    private $storeManager;
    private $logger;

    public function __construct(
        ShippingConfig $shippingConfig,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->shippingConfig = $shippingConfig;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function toOptionArray(): array
    {
        $options = [];
        
		$storeId = (int)$this->storeManager->getStore()->getId();

        
        // Method 1: Get carriers from shipping config (works for most)
        $allCarriers = $this->shippingConfig->getAllCarriers($storeId);
        
        foreach ($allCarriers as $carrierCode => $carrierModel) {
            try {
                $carrierTitle = $this->scopeConfig->getValue(
                    'carriers/' . $carrierCode . '/title',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: $this->getCarrierDefaultTitle($carrierCode);
                
                // Check if carrier is active
                $isActive = $this->scopeConfig->getValue(
                    'carriers/' . $carrierCode . '/active',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                
                if ($isActive) {
                    // Try to get methods
                    if (method_exists($carrierModel, 'getAllowedMethods')) {
                        $allowedMethods = $carrierModel->getAllowedMethods();
                        
                        if (is_array($allowedMethods) && !empty($allowedMethods)) {
                            foreach ($allowedMethods as $methodCode => $methodTitle) {
                                $options[] = [
                                    'value' => $carrierCode . '_' . $methodCode,
                                    'label' => $carrierTitle . ' - ' . $methodTitle
                                ];
                            }
                        } else {
                            // Single method or string response
                            $options[] = [
                                'value' => $carrierCode,
                                'label' => $carrierTitle
                            ];
                        }
                    } else {
                        // Fallback for carriers without getAllowedMethods
                        $options[] = [
                            'value' => $carrierCode,
                            'label' => $carrierTitle
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                // Log error but continue with other carriers
                $this->logger->warning('Error loading carrier: ' . $carrierCode . ' - ' . $e->getMessage());
                
                // Add carrier anyway with basic info
                $carrierTitle = $this->getCarrierDefaultTitle($carrierCode);
                $options[] = [
                    'value' => $carrierCode,
                    'label' => $carrierTitle . ' (Config Issue)'
                ];
            }
        }
        
        // Method 2: Scan all carrier configurations directly from database
        
        $additionalCarriers = $this->scanAllCarrierConfigurations((int)$storeId);

        foreach ($additionalCarriers as $carrier) {
            // Avoid duplicates
            $exists = false;
            foreach ($options as $option) {
                if (strpos($option['value'], $carrier['code']) === 0) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $options[] = $carrier;
            }
        }
        
        // Method 3: Add known third-party extensions manually
        
        $thirdPartyCarriers = $this->getKnownThirdPartyCarriers((int)$storeId);

        foreach ($thirdPartyCarriers as $carrier) {
            // Avoid duplicates
            $exists = false;
            foreach ($options as $option) {
                if ($option['value'] === $carrier['value']) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $options[] = $carrier;
            }
        }
        
        // Sort options by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        // Log discovered methods for debugging
        $this->logger->info('EasyOrder: Discovered shipping methods', [
            'count' => count($options),
            'methods' => array_column($options, 'value'),
            'store_id' => $storeId
        ]);
        
        return $options;
    }
    
    /**
     * Scan all carrier configurations from core_config_data
     */
   
	private function scanAllCarrierConfigurations($storeId): array

    {
        $carriers = [];
        
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            
            // Get all carrier configurations
            $select = $connection->select()
                ->from($resource->getTableName('core_config_data'))
                ->where('path LIKE ?', 'carriers/%/active')
                ->where('value = ?', '1')
                ->where('scope_id = ? OR scope_id = 0', $storeId);
                
            $results = $connection->fetchAll($select);
            
            foreach ($results as $result) {
                $pathParts = explode('/', $result['path']);
                if (count($pathParts) >= 2) {
                    $carrierCode = $pathParts[1];
                    
                    // Get carrier title
                    $titlePath = 'carriers/' . $carrierCode . '/title';
                    $title = $this->scopeConfig->getValue(
                        $titlePath,
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    ) ?: $this->getCarrierDefaultTitle($carrierCode);
                    
                    $carriers[] = [
                        'value' => $carrierCode,
                        'label' => $title . ' (DB Scan)',
                        'code' => $carrierCode
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error scanning carrier configurations: ' . $e->getMessage());
        }
        
        return $carriers;
    }
    
    /**
     * Get known third-party carriers
     */
    
	private function getKnownThirdPartyCarriers($storeId): array

    {
        $knownCarriers = [
            'mageplaza_shipping' => 'Mageplaza Shipping',
            'tablerate' => 'Table Rate Shipping',
            'matrixrate' => 'Matrix Rate Shipping',
            'storepickup' => 'Store Pickup',
            'aramex' => 'Aramex',
            'mylerz' => 'Mylerz',
            'bosta' => 'Bosta',
            'dhl' => 'DHL',
            'fedex' => 'FedEx',
            'ups' => 'UPS',
            'temando' => 'Temando',
            'webshopapps_matrixrate' => 'WebShopApps Matrix Rate'
        ];
        
        $carriers = [];
        
        foreach ($knownCarriers as $code => $title) {
            $isActive = $this->scopeConfig->getValue(
                'carriers/' . $code . '/active',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            if ($isActive) {
                $configTitle = $this->scopeConfig->getValue(
                    'carriers/' . $code . '/title',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: $title;
                
                $carriers[] = [
                    'value' => $code,
                    'label' => $configTitle
                ];
            }
        }
        
        return $carriers;
    }

    private function getCarrierDefaultTitle(string $carrierCode): string
    {
        $titles = [
            'flatrate' => 'Flat Rate',
            'freeshipping' => 'Free Shipping',
            'tablerate' => 'Table Rates',
            'ups' => 'UPS',
            'usps' => 'USPS',
            'fedex' => 'FedEx',
            'dhl' => 'DHL',
            'aramex' => 'Aramex',
            'mylerz' => 'Mylerz',
            'bosta' => 'Bosta',
            'mageplaza_shipping' => 'Mageplaza Shipping'
        ];
        
        return $titles[$carrierCode] ?? ucfirst(str_replace(['_', '-'], ' ', $carrierCode));
    }
}