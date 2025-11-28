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
    private CoverImageImport $helper;
    private ImageImportFactory $imageImportFactory;
    private CollectionFactory $collectionFactory;
    private ImageDownloader $imageDownloader;
    private CategoryImageUpdater $categoryImageUpdater;
    private ProductImageUpdater $productImageUpdater;
    private LoggerInterface $logger;

    public function __construct(
        CoverImageImport $helper,
        ImageImportFactory $imageImportFactory,
        CollectionFactory $collectionFactory,
        ImageDownloader $imageDownloader,
        CategoryImageUpdater $categoryImageUpdater,
        ProductImageUpdater $productImageUpdater,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
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

        $coverUrls = $this->helper->getAllCoverUrls();

        if (empty($coverUrls)) {
            $this->logger->warning('CoverImageImport: No magazines configured. Please configure magazines in Admin > Stores > Configuration > DieMayrei > Cover Import.');
            return;
        }

        foreach ($coverUrls as $coverKey => $url) {
            if (empty($url)) {
                continue;
            }

            $this->processCover($coverKey, $url);
        }

        $this->logger->info('CoverImageImport: Import finished.');
    }

    private function processCover(string $coverKey, string $url): void
    {
        $label = $this->helper->getLabel($coverKey);
        $this->logger->info("CoverImageImport: Processing {$label} ({$coverKey})...");

        try {
            $existingRecord = $this->getExistingRecord($url);
            $downloadResult = $this->imageDownloader->downloadAndResize($url, $existingRecord);

            if ($downloadResult === null) {
                // No update needed, but check for items without images
                if ($existingRecord) {
                    $this->categoryImageUpdater->updateCategories($coverKey, $existingRecord['imported'], true);
                    $this->productImageUpdater->updateProducts($coverKey, $existingRecord['imported'], true);
                }
                return;
            }

            // Save/update database record
            $this->saveImageRecord($coverKey, $url, $downloadResult['path'], $existingRecord);

            // Update categories and products
            $this->categoryImageUpdater->updateCategories($coverKey, $downloadResult['category_url']);
            $this->productImageUpdater->updateProducts($coverKey, $downloadResult['path']);

            $this->logger->info("CoverImageImport: Successfully updated {$label}");

        } catch (\Exception $e) {
            $this->logger->error("CoverImageImport: Error processing {$label}: " . $e->getMessage());
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

    private function saveImageRecord(string $coverKey, string $url, string $importedPath, ?array $existingRecord): void
    {
        $imageImport = $this->imageImportFactory->create();

        if ($existingRecord) {
            $imageImport->load($existingRecord['id']);
        }

        $imageImport->setData([
            'key' => $coverKey,
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
