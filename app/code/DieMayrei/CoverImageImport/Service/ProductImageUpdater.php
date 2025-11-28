<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Service;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Psr\Log\LoggerInterface;

class ProductImageUpdater
{
    private CollectionFactory $productCollectionFactory;
    private ProductFactory $productFactory;
    private ProductResource $productResource;
    private Gallery $gallery;
    private Filesystem $filesystem;
    private ImageDownloader $imageDownloader;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        ProductResource $productResource,
        Gallery $gallery,
        Filesystem $filesystem,
        ImageDownloader $imageDownloader,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->productResource = $productResource;
        $this->gallery = $gallery;
        $this->filesystem = $filesystem;
        $this->imageDownloader = $imageDownloader;
        $this->logger = $logger;
    }

    /**
     * Update product images for a given config path
     *
     * @param string $configPath
     * @param string $imagePath
     * @param bool $onlyMissing Only update products without images or with different cover
     */
    public function updateProducts(string $configPath, string $imagePath, bool $onlyMissing = false): void
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('cover', ['eq' => $configPath]);

        if ($onlyMissing) {
            $fileName = basename($imagePath);
            $pattern = $this->extractBasePattern($fileName);
            $collection->addFieldToFilter(
                [
                    ['attribute' => 'image', 'null' => true],
                    ['attribute' => 'image', 'nlike' => '%' . $pattern . '%']
                ]
            );
        }

        foreach ($collection as $product) {
            try {
                $this->logger->info('CoverImageImport: Updating product: ' . $product->getId());
                $this->updateProductImage($product->getId(), $imagePath);
            } catch (\Exception $e) {
                $this->logger->error('CoverImageImport: Failed to update product ' . $product->getId() . ': ' . $e->getMessage());
            }
        }
    }

    private function updateProductImage(int $productId, string $imagePath): void
    {
        $product = $this->productFactory->create()->load($productId);

        $product->addImageToMediaGallery(
            $imagePath,
            ['image', 'small_image', 'thumbnail', 'swatch_image'],
            false,
            false
        );

        $mediaGallery = $product->getData('media_gallery');
        $addedImage = array_pop($mediaGallery['images']);

        // Remove old images with same base pattern
        $removeValues = [];
        $addedPattern = $this->extractBasePattern($addedImage['file']);

        foreach ($mediaGallery['images'] as $existingImage) {
            $existingPattern = $this->extractBasePattern($existingImage['file']);
            if ($existingPattern === $addedPattern) {
                $removeValues[] = $existingImage['value_id'];
            }
        }

        // Insert new gallery entry
        $valueId = $this->gallery->insertGallery([
            'attribute_id' => 90,
            'media_type' => 'image',
            'value' => $addedImage['file'],
        ]);
        $this->gallery->bindValueToEntity($valueId, $productId);

        $this->gallery->insertGalleryValueInStore([
            'value_id' => $valueId,
            'entity_id' => $productId,
            'store_id' => 0,
            'position' => 0
        ]);

        // Remove old entries
        if (!empty($removeValues)) {
            $this->gallery->deleteGallery($removeValues);
        }

        // Save image attributes
        $this->productResource->saveAttribute($product, 'image');
        $this->productResource->saveAttribute($product, 'small_image');
        $this->productResource->saveAttribute($product, 'thumbnail');
        $this->productResource->saveAttribute($product, 'swatch_image');

        // Copy image to product media directory
        $this->copyImageToProductMedia($addedImage['file']);
    }

    private function extractBasePattern(string $filePath): string
    {
        $baseName = basename(parse_url($filePath, PHP_URL_PATH) ?? $filePath);
        $lastDash = strrpos($baseName, '-');
        return $lastDash !== false ? substr($baseName, 0, $lastDash) : $baseName;
    }

    private function copyImageToProductMedia(string $file): void
    {
        $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $tmpPath = $mediaPath . 'tmp/catalog/product' . $file;
        $destPath = $mediaPath . 'catalog/product' . $file;

        // Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            $ioFile = new IoFile();
            $ioFile->mkdir($destDir, 0775);
        }

        if (file_exists($tmpPath)) {
            copy($tmpPath, $destPath);
        }
    }
}
