<?php
namespace MagoArab\EasYorder\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use MagoArab\EasYorder\Model\QuickOrderService;
use Psr\Log\LoggerInterface;

class UpdateQuantity extends Action
{
    private $jsonFactory;
    private $quickOrderService;
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        QuickOrderService $quickOrderService,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->quickOrderService = $quickOrderService;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            $quoteId = (int)$this->getRequest()->getParam('quote_id');
            $newQty = (int)$this->getRequest()->getParam('qty');
            $shippingMethod = $this->getRequest()->getParam('shipping_method');
            $countryId = $this->getRequest()->getParam('country_id');
            $region = $this->getRequest()->getParam('region');
            $postcode = $this->getRequest()->getParam('postcode');
            
            if (!$quoteId || !$newQty || !$shippingMethod || !$countryId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Missing required parameters')
                ]);
            }
            
            $response = $this->quickOrderService->updateQuantityAndRecalculateShipping(
                $quoteId,
                $newQty,
                $shippingMethod,
                $countryId,
                $region,
                $postcode
            );
            
            return $result->setData($response);
            
        } catch (\Exception $e) {
            $this->logger->error('Error in UpdateQuantity controller: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('Error updating quantity: %1', $e->getMessage())
            ]);
        }
    }
}