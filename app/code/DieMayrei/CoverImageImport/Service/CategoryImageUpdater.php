<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Service;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CategoryImageUpdater
{
    private CollectionFactory $categoryCollectionFactory;
    private StoreManagerInterface $storeManager;
    private ImageDownloader $imageDownloader;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        ImageDownloader $imageDownloader,
        LoggerInterface $logger
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->imageDownloader = $imageDownloader;
        $this->logger = $logger;
    }

    /**
     * Update category images for a given config path
     *
     * @param string $configPath
     * @param string $imageUrl
     * @param bool $onlyMissing Only update categories without images
     */
    public function updateCategories(string $configPath, string $imageUrl, bool $onlyMissing = false): void
    {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('cover_category', ['eq' => $configPath]);

        if ($onlyMissing) {
            // Convert file path to URL if needed
            if (strpos($imageUrl, 'http') !== 0) {
                $fileName = basename($imageUrl);
                $imageUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
                    . $this->imageDownloader->getTargetDir() . '/' . $this->imageDownloader->getResizeWidth() . '/' . $fileName;
            }
            $collection->addFieldToFilter('image', ['null' => true]);
        }

        foreach ($collection as $category) {
            try {
                $this->logger->info('CoverImageImport: Updating category: ' . $category->getName());
                $category->setCustomAttribute('image', $imageUrl);
                $category->save();
            } catch (\Exception $e) {
                $this->logger->error('CoverImageImport: Failed to update category ' . $category->getId() . ': ' . $e->getMessage());
            }
        }
    }
}
