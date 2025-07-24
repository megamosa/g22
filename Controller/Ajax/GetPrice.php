<?php
namespace MagoArab\EasYorder\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class GetPrice implements HttpPostActionInterface
{
    private $jsonFactory;
    private $request;
    private $productRepository;
    private $priceHelper;

    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        ProductRepositoryInterface $productRepository,
        PriceHelper $priceHelper
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->priceHelper = $priceHelper;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        
        try {
            $productId = (int)$this->request->getParam('product_id');
            $superAttribute = $this->request->getParam('super_attribute', []);
            
            $product = $this->productRepository->getById($productId);
            
            if ($product->getTypeId() === 'configurable' && !empty($superAttribute)) {
                $childProduct = $product->getTypeInstance()->getProductByAttributes($superAttribute, $product);
                if ($childProduct) {
                    $price = $childProduct->getFinalPrice();
                } else {
                    $price = $product->getFinalPrice();
                }
            } else {
                $price = $product->getFinalPrice();
            }
            
            return $result->setData([
                'success' => true,
                'price' => $price,
                'formatted_price' => $this->priceHelper->currency($price, true, false)
            ]);
            
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error getting product price')
            ]);
        }
    }
}