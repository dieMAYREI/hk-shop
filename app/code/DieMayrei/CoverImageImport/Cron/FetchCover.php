<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Cron;

use DieMayrei\CoverImageImport\Helper\CoverImageImport;
use DieMayrei\CoverImageImport\Model\ImageImportFactory;
use DieMayrei\CoverImageImport\Model\ResourceModel\ImageImport\CollectionFactory;
use DieMayrei\CoverImageImport\Service\ImageDownloader;
use DieMayrei\CoverImageImport\Service\CategoryImageUpdater;
use DieMayrei\CoverImageImport\Service\ProductImageUpdater;
use Psr\Log\LoggerInterface;

class FetchCover
{
    private CoverImageImport $config;
    private ImageImportFactory $imageImportFactory;
    private CollectionFactory $collectionFactory;
    private ImageDownloader $imageDownloader;
    private CategoryImageUpdater $categoryImageUpdater;
    private ProductImageUpdater $productImageUpdater;
    private LoggerInterface $logger;

    public function __construct(
        CoverImageImport $config,
        ImageImportFactory $imageImportFactory,
        CollectionFactory $collectionFactory,
        ImageDownloader $imageDownloader,
        CategoryImageUpdater $categoryImageUpdater,
        ProductImageUpdater $productImageUpdater,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->imageImportFactory = $imageImportFactory;
        $this->collectionFactory = $collectionFactory;
        $this->imageDownloader = $imageDownloader;
        $this->categoryImageUpdater = $categoryImageUpdater;
        $this->productImageUpdater = $productImageUpdater;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->logger->info('CoverImageImport: Starting import...');

        foreach ($this->config->getCoverArray() as $configPath => $label) {
            if (empty($configPath)) {
                continue; // Skip 'kein'
            }

            $this->processConfigPath($configPath);
        }

        $this->logger->info('CoverImageImport: Import finished.');
    }

    private function processConfigPath(string $configPath): void
    {
        $url = $this->config->getConfig($configPath);

        if (empty($url)) {
            return;
        }

        try {
            $existingRecord = $this->getExistingRecord($url);
            $downloadResult = $this->imageDownloader->downloadAndResize($url, $existingRecord);

            if ($downloadResult === null) {
                // No update needed, but check for items without images
                if ($existingRecord) {
                    $this->categoryImageUpdater->updateCategories($configPath, $existingRecord['imported'], true);
                    $this->productImageUpdater->updateProducts($configPath, $existingRecord['imported'], true);
                }
                return;
            }

            // Save/update database record
            $this->saveImageRecord($configPath, $url, $downloadResult['path'], $existingRecord);

            // Update categories and products
            $this->categoryImageUpdater->updateCategories($configPath, $downloadResult['category_url']);
            $this->productImageUpdater->updateProducts($configPath, $downloadResult['path']);

        } catch (\Exception $e) {
            $this->logger->error('CoverImageImport: Error processing ' . $configPath . ': ' . $e->getMessage());
        }
    }

    private function getExistingRecord(string $url): ?array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToSelect(['id', 'imported'])
            ->addFieldToFilter('origin', $url);

        if ($collection->getSize() > 0) {
            return $collection->getFirstItem()->getData();
        }

        return null;
    }

    private function saveImageRecord(string $configPath, string $url, string $importedPath, ?array $existingRecord): void
    {
        $imageImport = $this->imageImportFactory->create();

        if ($existingRecord) {
            $imageImport->load($existingRecord['id']);
        }

        $imageImport->setData([
            'key' => $configPath,
            'origin' => $url,
            'imported' => $importedPath,
            'last_updated' => date('Y-m-d H:i:s')
        ]);

        if ($existingRecord) {
            $imageImport->setId($existingRecord['id']);
        }

        $imageImport->save();
    }
}
