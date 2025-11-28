<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Block\Category;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class View extends Template
{
    private Registry $coreRegistry;
    private StoreManagerInterface $storeManager;
    private CategoryRepository $categoryRepository;
    private CollectionFactory $categoryCollectionFactory;

    /**
     * Default support contact information
     */
    private const DEFAULT_SUPPORT = [
        'zeitschriften' => [
            'tel' => [
                'src' => '+49 (0)89/12705-487',
                'headline' => 'Zeitschriften',
                'description' => 'Falls Sie Fragen zu unseren Zeitschriften haben, können Sie uns von Montag bis Freitag zwischen 07:30 – 19.00 Uhr unter folgender Nummer erreichen:'
            ],
            'email' => [
                'src' => 'kundenservice@dlv.de',
                'headline' => 'Zeitschriften',
                'description' => 'Falls Sie Fragen zu unseren Zeitschriften haben, können Sie uns jederzeit per E-Mail kontaktieren.'
            ]
        ],
        'produkte' => [
            'tel' => [
                'src' => '+49 (0)89/12705-228',
                'headline' => 'Bücher & Produkte',
                'description' => 'Falls Sie Fragen zu unseren Büchern & Produkten haben, können Sie uns von Montag bis Freitag zwischen 07:30 – 19.00 Uhr unter folgender Nummer erreichen:'
            ],
            'email' => [
                'src' => 'produkt@dlv.de',
                'headline' => 'Bücher & Produkte',
                'description' => 'Falls Sie Fragen zu unseren Büchern & Produkten haben, können Sie uns jederzeit per E-Mail kontaktieren.'
            ]
        ]
    ];

    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        CollectionFactory $categoryCollectionFactory,
        CategoryRepository $categoryRepository,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get current category
     *
     * @return \Magento\Catalog\Model\Category|null
     */
    public function getCurrCategory(): ?\Magento\Catalog\Model\Category
    {
        return $this->coreRegistry->registry('current_category');
    }

    /**
     * Get category by ID
     *
     * @param int $id
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCategoryById(int $id): \Magento\Catalog\Api\Data\CategoryInterface
    {
        return $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
    }

    /**
     * Get categories by level
     *
     * @param int $level
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoryByLevel(int $level): \Magento\Catalog\Model\ResourceModel\Category\Collection
    {
        return $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('level', ['eq' => $level])
            ->addIsActiveFilter();
    }

    /**
     * Get store base URL by root category ID
     *
     * @param int $categoryId
     * @return string|null
     */
    public function getStoreByCategory(int $categoryId): ?string
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getRootCategoryId() == $categoryId) {
                return $store->getBaseUrl();
            }
        }
        return null;
    }

    /**
     * Get support email - checks current category and parent categories
     *
     * @return mixed
     */
    public function getSupportEmail()
    {
        return $this->getSupportAttribute('cat_support_email', 'email');
    }

    /**
     * Get support telephone - checks current category and parent categories
     *
     * @return mixed
     */
    public function getSupportTel()
    {
        return $this->getSupportAttribute('cat_support_tel', 'tel');
    }

    /**
     * Get support attribute value from category hierarchy
     *
     * @param string $attributeCode
     * @param string $defaultKey
     * @return mixed
     */
    private function getSupportAttribute(string $attributeCode, string $defaultKey)
    {
        $defaults = [
            'zeitschriften' => self::DEFAULT_SUPPORT['zeitschriften'][$defaultKey],
            'produkte' => self::DEFAULT_SUPPORT['produkte'][$defaultKey]
        ];

        $currentCategory = $this->getCurrCategory();
        if (!$currentCategory) {
            return $defaults;
        }

        // Check current category
        $attribute = $currentCategory->getCustomAttribute($attributeCode);
        if ($attribute && $attribute->getValue()) {
            return $attribute->getValue();
        }

        // Check parent categories (from nearest to root)
        $parentIds = $currentCategory->getParentIds();
        if (is_array($parentIds)) {
            foreach (array_reverse($parentIds) as $parentId) {
                try {
                    $parentCategory = $this->getCategoryById((int) $parentId);
                    $parentAttribute = $parentCategory->getCustomAttribute($attributeCode);
                    if ($parentAttribute && $parentAttribute->getValue()) {
                        return $parentAttribute->getValue();
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $defaults;
    }
}
