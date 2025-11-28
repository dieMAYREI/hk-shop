<?php

namespace Diemayrei\CoverImageImport\Cron;

use Diemayrei\CoverImageImport\Helper\CoverImageImport;
use Carbon\Carbon;
use Diemayrei\CoverImageImport\Model\ResourceModel\ImageImport;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Controller\Adminhtml\Cache\CleanImages;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Diemayrei\CoverImageImport\Model\ImageImportFactory;
use Diemayrei\CoverImageImport\Model\ResourceModel\ImageImport\Collection;
use MageWorx\OptionFeatures\Model\ResourceModel\Image;
use MageWorx\OptionFeatures\Model\ImageFactory;

class FetchCover
{

    /**
     * @var CoverImageImport
     */
    protected $config;
    protected $apiQuality = 'w=1024';
    protected $targetDir = 'cover';
    protected $targetCategoryDir = 'catalog/category/import';
    protected $categoryImageWidth = 545;
    protected $categoryImageHeight = 694;
    protected $downloadedImages = [];
    protected $pulledImages = [];

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /** @var ObjectManager */
    protected $objectManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var AdapterFactory
     */
    protected $imageFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var Product
     */
    protected $productResourceModel;

    /**
     * @var Gallery
     */
    protected $gallery;

    /** @var CleanImages */
    protected $cleanImage;

    protected $cacheTypeList;

    protected $cacheFrontendPool;

    protected $productModel;

    protected $imageProcessor;

    /** @var Magento\Framework\App\State  */
    protected $appState;

    /** @var ImageImport */
    protected $_imageImport;

    /** @var Image */
    protected $_mageworx;

    /**
     * FetchCover constructor.
     * @param CoverImageImport $config
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param Filesystem $filesystem
     * @param AdapterFactory $imageFactory
     * @param ProductFactory $productFactory
     * @param Product $productResourceModel
     * @param Gallery $mediaGalleryResourceModel
     */
    public function __construct(
        CoverImageImport $config,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        Filesystem $filesystem,
        AdapterFactory $imageFactory,
        ProductFactory $productFactory,
        Product $productResourceModel,
        Gallery $mediaGalleryResourceModel,
        CleanImages $imgClean,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\Product\Gallery\Processor $imageProcessor,
        \Magento\Framework\App\State $appState,
        ImageImportFactory $imageImport,
        \Psr\Log\LoggerInterface $logger,
        ImageFactory $mageworx
    ) {
        $this->appState = $appState;
        $this->filesystem = $filesystem;
        $this->imageFactory = $imageFactory;
        $this->productFactory = $productFactory;
        $this->productResourceModel = $productResourceModel;
        $this->gallery = $mediaGalleryResourceModel;
        $this->cleanImage = $imgClean;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->productModel = $productModel;
        $this->imageProcessor = $imageProcessor;
        $this->_imageImport = $imageImport;
        $this->_mageworx = $mageworx;

        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->objectManager = ObjectManager::getInstance();
        $this->storeManager = $storeManager;
        $this->storeManager->setCurrentStore(0);
        $this->logger = $logger;
    }


    /**
     * @return $this
     * @throws \Exception
     */
    public function execute()
    {
        $this->checkForUpdates();

        $this->logger->info('CoverImageImport finished!!!');
    }

    private function checkForUpdates()
    {
        $additional_imags = [
            'diemayrei/cover_import/agrarheute_rind_cover',
            'diemayrei/cover_import/agrarheute_schwein_cover',
            'diemayrei/cover_import/agrarheute_energie_cover'
        ];

        foreach ($this->config->getCoverArray() as $key => $item) {
            if ($key && $this->config->getConfig($key)) {
                $url = $this->config->getConfig($key);
                $varPath = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath();

                $fileName = basename($url);
                $imageContent = file_get_contents($url . '?' . $this->apiQuality);

                /** @var Collection $collection */
                $collection = $this->_imageImport->create()->getCollection()
                    ->addFieldToSelect('id')
                    ->addFieldToSelect('imported')
                    ->addFieldToFilter('origin', $url);

                $ext = pathinfo($url, PATHINFO_EXTENSION);

                if (!$ext) {
                    $ext = 'jpg';
                }

                $fileName = basename($url, "." . $ext) . '-' . time() . '.' . $ext;
                $originalUrl = $varPath . $this->targetCategoryDir . '/' . $fileName;

                if ($collection->count()) {
                    $idEntity = $collection->getFirstItem()->toArray();
                    try {
                        if (strlen($imageContent) != filesize($idEntity['imported'])) {
                            $fileUrl = $this->downloadImages($fileName, $imageContent, $varPath, $originalUrl, $idEntity['imported']);

                            $imageTable = $this->_imageImport->create();
                            $updateTable = $imageTable->load($idEntity['id']);
                            $updateTable->setImported($originalUrl);
                            $updateTable->setLastUpdated(Carbon::now());
                            $updateTable->save();

                            $this->setCategoryImages($key, $fileUrl['category']);
                            $this->setProductImages($key, $fileUrl['product']);

                            if (in_array($key, $additional_imags)) {
                                $this->setAdditionalImages($key, $fileUrl['product']);
                            }
                        } else {
                            /**
                             * Produkte / Kategorien die noch kein Bild haben
                             */
                            $this->setCategoryImages($key, $idEntity['imported'], true);
                            $this->setProductImages($key, $idEntity['imported'], true);
                        }
                    } catch (\Exception $e) {
                        $delete = $this->_imageImport->create();
                        $delete->load($idEntity['id']);
                        $delete->delete();
                        if (file_exists($idEntity['imported'])) {
                            unlink($idEntity['imported']);
                        }
                        $this->logger->info($e->getMessage());
                    }
                } else {

                    $fileUrl = $this->downloadImages($fileName, $imageContent, $varPath, $originalUrl);

                    $imageTable = $this->_imageImport->create();
                    $imageTable->setData([
                        'key' => $key,
                        'origin' => $url,
                        'imported' => $originalUrl,
                        'last_updated' => Carbon::now()
                    ]);
                    $imageTable->save();

                    $this->setCategoryImages($key, $fileUrl['category']);
                    $this->setProductImages($key, $fileUrl['product']);

                    if (in_array($key, $additional_imags)) {
                        $this->setAdditionalImages($key, $fileUrl['product']);
                    }
                }
            }
        }
    }

    private function downloadImages($fileName, $imageContent, $varPath, $originalUrl, $toDelete = false)
    {
        $this->logger->info('Download Image: ' . $fileName);

        $ioAdapter = new Filesystem\Io\File();
        $ioAdapter->open(['path' => $varPath . $this->targetCategoryDir]);
        $ioAdapter->write($fileName, $imageContent);

        if (file_exists($toDelete)) {
            unlink($toDelete);
        }

        return $this->resize($originalUrl, 600);
    }

    private function setAdditionalImages($key, $url)
    {

        $arImagePath = pathinfo($url);
        $relImagePath = substr($arImagePath['basename'], 0, 1) . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 1, 1) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->getMediaDirMagewWorx() . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 0, 1))) {
            if (!mkdir($concurrentDirectory = $this->getMediaDirMagewWorx() . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 0, 1)) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        if (!is_dir($this->getMediaDirMagewWorx() . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 0, 1) . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 1, 1))) {
            if (!mkdir($concurrentDirectory = $this->getMediaDirMagewWorx() . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 0, 1) . DIRECTORY_SEPARATOR . substr($arImagePath['basename'], 1, 1)) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        $resized = $this->resize($url, 260);
        copy($resized['product'], $this->getMediaDirMagewWorx() . DIRECTORY_SEPARATOR . $relImagePath . basename($url));

        $pattern = '';
        switch ($key):
            case 'diemayrei/cover_import/agrarheute_rind_cover':
                $pattern = 'rind';
                break;
            case 'diemayrei/cover_import/agrarheute_schwein_cover':
                $pattern = 'schwein';
                break;
            case 'diemayrei/cover_import/agrarheute_energie_cover':
                $pattern = 'energie';
                break;
        endswitch;


        /** @var Image $mageWorx */
        $mageWorx = $this->_mageworx->create()->getCollection()
            ->addFieldToSelect('option_type_image_id')
            ->addFieldToSelect('value')
            ->addFieldToFilter(
                'value',
                ['like' => '%' . $pattern . '%']
            );
        foreach ($mageWorx as $imageData) {
            $mageTable = $this->_mageworx->create();
            $updateTable = $mageTable->load($imageData['option_type_image_id']);
            $updateTable->setValue(DIRECTORY_SEPARATOR . $relImagePath . basename($url));
            $updateTable->save();
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function setCategoryImages($key, $fileUrl, $force = false)
    {
        /** @var  \Magento\CatalogSearch\Model\ResourceModel\Advanced\Collection\Interceptor $categoryCollection */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*')
            ->addAttributeToFilter(
                'cover_category',
                ['eq' => $key]
            );
        if ($force) {
            $image = substr($fileUrl, strrpos($fileUrl, '/') + 1);
            $fileUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $this->targetCategoryDir . '/600/' . $image;
            $categoryCollection->addFieldToFilter(
                'image',
                ['null' => true]
            );
        }
        /** @var \Magento\Catalog\Model\Category\Interceptor $category */
        foreach ($categoryCollection as $category) {
            try {
                $this->logger->info('Category: ' . $category->getName());
                $category->setCustomAttribute('image', $fileUrl);
                $category->save();
            } catch (\Exception $e) {
                $this->logger->info($e->getMessage());
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function setProductImages($key, $fileUrl, $force = false)
    {
        /** @var  \Magento\CatalogSearch\Model\ResourceModel\Advanced\Collection\Interceptor $productCollection */
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToSelect('*')
            ->addAttributeToFilter(
                'cover',
                ['eq' => $key]
            );

        if ($force) {
            $fileName = basename($fileUrl);
            $pattern = substr(basename(parse_url($fileUrl, PHP_URL_PATH)), 0, strrpos(basename(parse_url($fileUrl, PHP_URL_PATH)), '-'));
            $fileUrl = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath($this->targetCategoryDir . '/') . '600/' . $fileName;
            $productCollection->addFieldToFilter(
                [
                    [
                        'attribute' => 'image',
                        'null' => true
                    ],
                    [
                        'attribute' => 'image',
                        'nlike' => '%' . $pattern . '%'
                    ]
                ]
            );
        }

        /** @var Interceptor $product */
        foreach ($productCollection as $product) {
            try {
                $this->logger->info('Product: ' . $product->getId());
                $this->setGalleryImagesForStore($product->getId(), 0, $fileUrl);
            } catch (\Exception $e) {
                $this->logger->info($e->getMessage());
            }
        }
    }

    /**
     * @param $productId
     * @param $storeId
     * @param $image
     * @throws LocalizedException
     */
    protected function setGalleryImagesForStore($productId, $storeId, $image)
    {
        try {
            $productImage = $this->productFactory->create()->load($productId);

            $productImage->addImageToMediaGallery(
                $image,
                ['image', 'small_image', 'thumbnail', 'swatch_image'],
                false,
                false
            );

            $mediaGallery = $productImage->getData('media_gallery');

            $addedImage = array_pop($mediaGallery['images']);

            $removeValues = [];
            foreach ($mediaGallery['images'] as $prodImage) {
                $existingImage = substr(basename(parse_url($prodImage['file'], PHP_URL_PATH)), 0, strrpos(basename(parse_url($prodImage['file'], PHP_URL_PATH)), '-'));
                $addedImageinGallery = substr(basename(parse_url($addedImage['file'], PHP_URL_PATH)), 0, strrpos(basename(parse_url($addedImage['file'], PHP_URL_PATH)), '-'));
                if (substr_count($prodImage['file'], $addedImageinGallery)) {
                    $removeValues[] = $prodImage['value_id'];
                }
            }

            $valueId = $this->gallery->insertGallery([
                "attribute_id" => 90,
                "media_type" => 'image',
                "value" => $addedImage['file'],
            ]);
            $this->gallery->bindValueToEntity($valueId, $productId);

            $this->gallery->insertGalleryValueInStore([
                "value_id" => $valueId,
                "entity_id" => $productId,
                "store_id" => $storeId,
                'position' => 0
            ]);

            $this->gallery->deleteGallery($removeValues);

            $this->productResourceModel->saveAttribute($productImage, 'image');
            $this->productResourceModel->saveAttribute($productImage, 'small_image');
            $this->productResourceModel->saveAttribute($productImage, 'thumbnail');
            $this->productResourceModel->saveAttribute($productImage, 'swatch_image');

            $this->createProductDir($this->getMediaDir() . $addedImage['file']);

            copy(
                $this->getMediaDirTmp() . $addedImage['file'],
                $this->getMediaDir() . $addedImage['file']
            );
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }

    /**
     * @param $path
     */
    private function createImportDir($path)
    {
        $directoryList = new DirectoryList($path);

        if (!is_dir($directoryList->getRoot())) {
            $ioAdapter = new Filesystem\Io\File();
            $ioAdapter->mkdir($directoryList->getRoot(), 0775);
        }
    }

    /**
     * @param $path
     */
    private function createProductDir($path)
    {
        $directoryList = new DirectoryList(substr($path, 0, strrpos($path, '/')));

        if (!is_dir($directoryList->getRoot())) {
            $ioAdapter = new Filesystem\Io\File();
            $ioAdapter->mkdir($directoryList->getRoot(), 0775);
        }
    }

    /**
     * @param $image
     * @param null $width
     * @param null $height
     * @return string
     * @throws NoSuchEntityException
     */
    public function resize($url, $width = null, $height = null)
    {
        $this->createImportDir($this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath() . $this->targetCategoryDir . '/' . $width);
        $paths = [];

        $absolutePath = $url;

        $fileName = basename($url);

        $imageResized = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath($this->targetCategoryDir . '/') . $width . '/' . $fileName;

        $image = new \Imagick($absolutePath);
        $image->setImageUnits(\Imagick::RESOLUTION_PIXELSPERINCH);
        $image->setImageResolution(72, 72);
        $image->resizeImage($width, $width, \Imagick::FILTER_LANCZOS, 0.9, true);
        $image->setCompressionQuality(70);
        $image->writeImage($imageResized);
        $paths['product'] = $imageResized;
        $paths['category'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $this->targetCategoryDir . '/' . $width . '/' . $fileName;

        return $paths;
    }

    protected function getMediaDirTmp()
    {
        /** @var \Magento\Framework\App\Filesystem\DirectoryList $dir */
        $dir = $this->objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');
        $dir->getPath('media');
        return $dir->getPath('media') . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product';
    }

    protected function getMediaDir()
    {
        /** @var \Magento\Framework\App\Filesystem\DirectoryList $dir */
        $dir = $this->objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');
        $dir->getPath('media');
        return $dir->getPath('media') . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product';
    }

    protected function getMediaDirMagewWorx()
    {


        /** @var \Magento\Framework\App\Filesystem\DirectoryList $dir */
        $dir = $this->objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');

        if (!is_dir($dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx')) {
            if (!mkdir($concurrentDirectory = $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        if (!is_dir($dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures')) {
            if (!mkdir($concurrentDirectory = $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        if (!is_dir($dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product')) {
            if (!mkdir($concurrentDirectory = $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        if (!is_dir($dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'option')) {
            if (!mkdir($concurrentDirectory = $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'option') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        if (!is_dir($dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'option' . DIRECTORY_SEPARATOR . 'value')) {
            if (!mkdir($concurrentDirectory = $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'option' . DIRECTORY_SEPARATOR . 'value') && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $dir->getPath('media');
        return $dir->getPath('media') . DIRECTORY_SEPARATOR . 'mageworx' . DIRECTORY_SEPARATOR . 'optionfeatures' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . 'option' . DIRECTORY_SEPARATOR . 'value';
    }
}
