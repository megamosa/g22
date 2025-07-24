<?php
declare(strict_types=1);

namespace MagoArab\EasYorder\Controller\Ajax;

use MagoArab\EasYorder\Api\QuickOrderServiceInterface;
use MagoArab\EasYorder\Helper\Data as HelperData;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class Coupon implements HttpPostActionInterface
{
    private $request;
    private $jsonFactory;
    private $quickOrderService;
    private $helperData;
    private $cartRepository;
    private $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        QuickOrderServiceInterface $quickOrderService,
        HelperData $helperData,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->quickOrderService = $quickOrderService;
        $this->helperData = $helperData;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->helperData->isEnabled()) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Quick order is not enabled.')
                ]);
            }

            $action = $this->request->getParam('action'); // 'apply' or 'remove'
            $couponCode = trim($this->request->getParam('coupon_code', ''));
            $quoteId = (int)$this->request->getParam('quote_id');

            if (!$quoteId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Quote ID is required')
                ]);
            }

            $quote = $this->cartRepository->get($quoteId);

            if ($action === 'apply') {
                if (empty($couponCode)) {
                    return $result->setData([
                        'success' => false,
                        'message' => __('Coupon code is required')
                    ]);
                }
                
                $response = $this->quickOrderService->applyCouponCode($quote, $couponCode);
            } elseif ($action === 'remove') {
                $response = $this->quickOrderService->removeCouponCode($quote);
            } else {
                return $result->setData([
                    'success' => false,
                    'message' => __('Invalid action')
                ]);
            }

            return $result->setData($response);

        } catch (\Exception $e) {
            $this->logger->error('Coupon action error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('Unable to process coupon action.')
            ]);
        }
    }
}