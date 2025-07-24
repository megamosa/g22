<?php
/**
 * MagoArab_EasYorder Payment Methods Source Model
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
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class PaymentMethods
 * 
 * Provides payment method options from system configuration
 */
class PaymentMethods implements OptionSourceInterface
{
    /**
     * @var PaymentConfig
     */
    private $paymentConfig;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     */
    public function __construct(
        PaymentConfig $paymentConfig,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->paymentConfig = $paymentConfig;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $storeId = $this->storeManager->getStore()->getId();
        
        // Get all active payment methods from system configuration
        $activePaymentMethods = $this->paymentConfig->getActiveMethods();
        
        foreach ($activePaymentMethods as $methodCode => $methodConfig) {
            // Check if payment method is enabled
            $isActive = $this->scopeConfig->getValue(
                'payment/' . $methodCode . '/active',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            if ($isActive) {
                $title = $this->scopeConfig->getValue(
                    'payment/' . $methodCode . '/title',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: $this->getPaymentMethodDefaultTitle($methodCode);
                
                $options[] = [
                    'value' => $methodCode,
                    'label' => $title
                ];
            }
        }
        
        // Sort options by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        return $options;
    }

    /**
     * Get default title for payment method
     *
     * @param string $methodCode
     * @return string
     */
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
}