<?php
/**
 * MagoArab_EasYorder Ajax Regions Controller
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace MagoArab\EasYorder\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Directory\Model\ResourceModel\Region\Collection as RegionCollection;
use Psr\Log\LoggerInterface;

/**
 * Class Regions
 * 
 * Ajax controller for getting regions by country
 */
class Regions implements HttpGetActionInterface
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
     * @var RegionCollection
     */
    private $regionCollection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        RegionCollection $regionCollection,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->regionCollection = $regionCollection;
        $this->logger = $logger;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $countryId = trim($this->request->getParam('country_id'));

            if (!$countryId) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Country ID is required.')
                ]);
            }

            // Get regions for the specified country
            $regions = [];
            $regionCollection = clone $this->regionCollection;
            $regionCollection->addCountryFilter($countryId)->load();

            foreach ($regionCollection as $region) {
                $regions[] = [
                    'value' => $region->getId(),
                    'label' => $region->getName(),
                    'code' => $region->getCode()
                ];
            }

            return $result->setData([
                'success' => true,
                'regions' => $regions,
                'has_regions' => !empty($regions)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting regions: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => __('Unable to get regions.')
            ]);
        }
    }
}