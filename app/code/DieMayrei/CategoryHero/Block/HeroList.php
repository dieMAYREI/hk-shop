<?php

declare(strict_types=1);

namespace DieMayrei\CategoryHero\Block;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class HeroList extends Template
{
    private CollectionFactory $categoryCollectionFactory;

    private StoreManagerInterface $storeManager;

    private ?array $heroes = null;

    public function __construct(
        Context $context,
        CollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @return array<array<string, string>>
     */
    public function getHeroItems(): array
    {
        if ($this->heroes !== null) {
            return $this->heroes;
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->addAttributeToSelect(['name', 'url_key', 'hero_image', 'hero_is_active']);
        $collection->addIsActiveFilter();
        $collection->addAttributeToFilter('level', ['gt' => 1]);
        $collection->addAttributeToSort('position');

        $heroes = [];
        foreach ($collection as $category) {
            if (!(int) $category->getData('hero_is_active')) {
                continue;
            }

            $rawImage = (string) $category->getData('hero_image');
            if ($rawImage === '') {
                continue;
            }

            $imageUrl = $this->buildImageUrl((string) $category->getData('hero_image'));
            if (!$imageUrl) {
                continue;
            }

            $heroes[] = [
                'image' => $imageUrl,
                'alt' => (string) $category->getName(),
                'link' => $category->getUrl(),
            ];
        }

        $this->heroes = $heroes;

        return $this->heroes;
    }

    private function buildImageUrl(string $image): ?string
    {
        $image = trim($image);
        if ($image === '') {
            return null;
        }

        $image = ltrim($image, '/');

        if (strpos($image, 'media/') === 0) {
            $image = substr($image, strlen('media/'));
        }

        if (strpos($image, 'pub/') === 0) {
            $image = substr($image, strlen('pub/'));
        }

        if (strpos($image, 'catalog/category/') !== 0) {
            $image = 'catalog/category/' . ltrim($image, '/');
        }

        return rtrim($this->getMediaBaseUrl(), '/') . '/' . ltrim($image, '/');
    }

    private function getMediaBaseUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }
}
