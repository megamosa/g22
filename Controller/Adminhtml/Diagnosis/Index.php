<?php
declare(strict_types=1);

namespace MagoArab\EasYorder\Controller\Adminhtml\Diagnosis;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use MagoArab\EasYorder\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    private $resultPageFactory;
    private $helperData;
    private $logger;
    
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        HelperData $helperData,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->helperData = $helperData;
        $this->logger = $logger;
    }
    
    public function execute()
    {
        // Run diagnosis
        $diagnosis = $this->helperData->diagnoseShippingConfiguration();
        
        // Log diagnosis results
        $this->logger->info('EasYorder Shipping Diagnosis', $diagnosis);
        
        // Display results
        $this->messageManager->addNoticeMessage(
            'Shipping diagnosis completed. Check logs for detailed results.'
        );
        
        if (!$diagnosis['has_active_carriers']) {
            $this->messageManager->addErrorMessage(
                'No active shipping carriers found! Please enable at least one shipping method in Stores > Configuration > Sales > Shipping Methods.'
            );
        }
        
        if (!$diagnosis['shipping_origin']['configured']) {
            $this->messageManager->addErrorMessage(
                'Shipping origin not configured! Please set shipping origin in Stores > Configuration > Sales > Shipping Settings > Origin.'
            );
        }
        
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('EasyOrder Shipping Diagnosis'));
        
        return $resultPage;
    }
}