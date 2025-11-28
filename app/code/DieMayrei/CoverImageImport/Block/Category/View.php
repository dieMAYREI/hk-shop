<?php
namespace Diemayrei\CoverImageImport\Block\Category;

class View extends \Magento\Framework\View\Element\Template
{

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    protected $_storeManager;

    protected $_categoryRepository;

    protected $_categoryCollection;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry                      $registry
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        $this->_resource = $resource;
        $this->_storeManager = $storeManager;
        $this->_categoryRepository = $categoryRepository;
        $this->_categoryCollection = $categoryCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current product model
     *
     * @return \Magento\Catalog\Model\Product
     */
    public function getCurrCategory()
    {
        return $this->_coreRegistry->registry('current_category');
    }

    public function getCategoryById($id)
    {
        return $this->_categoryRepository->get($id, $this->_storeManager->getStore()->getId());
    }

    public function getCategoryByLevel($level)
    {
        $collection = $this->_categoryCollection->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('level', ['eq'=> $level]);
        $collection->addIsActiveFilter();

        return $collection;
    }

    public function getStoreByCategory($id)
    {
        foreach ($this->_storeManager->getStores() as $store) {
            if ($store->getRootCategoryId() == $id) {
                return $store->getBaseUrl();
            }
        }
    }

    public function getSupportEmail()
    {
        $email = [
            'zeitschriften' => [
                'src' => 'kundenservice@dlv.de',
                'headline' => 'Zeitschriften',
                'description' => 'Falls Sie Fragen zu unseren Zeitschriften haben, können Sie uns jederzeit per E-Mail kontaktieren.'
            ],
            'produkte' => [
                'src' => 'produkt@dlv.de',
                'headline' => 'Bücher & Produkte',
                'description' => 'Falls Sie Fragen zu unseren Büchern & Produkten haben, können Sie uns jederzeit per E-Mail kontaktieren.'
            ]
        ];
        if ($currentCat = $this->getCurrCategory()) {
            if ($currentCat->getCustomAttribute('cat_support_email')) {
                $email = $currentCat->getCustomAttribute('cat_support_email')->getValue();
            } else {
                if (is_array($currentCat->getParentIds())) {
                    foreach (array_reverse($currentCat->getParentIds()) as $pcat) {
                        if ($parentEmail = $this->getCategoryById($pcat)->getCustomAttribute('cat_support_email')) {
                            $email = $parentEmail->getValue();
                            break;
                        }
                    }
                }
            }
        }
        return $email;
    }
    public function getSupportTel()
    {
        $tel = [
            'zeitschriften' => [
                'src' => '+49 (0)89/12705-487',
                'headline' => 'Zeitschriften',
                'description' => 'Falls Sie Fragen zu unseren Zeitschriften haben, können Sie uns von Montag bis Freitag zwischen 07:30 – 19.00 Uhr unter folgender Nummer erreichen:'
            ],
            'produkte' => [
                'src' => '+49 (0)89/12705-228',
                'headline' => 'Bücher & Produkte',
                'description' => 'Falls Sie Fragen zu unseren Büchern & Produkten haben, können Sie uns von Montag bis Freitag zwischen 07:30 – 19.00 Uhr unter folgender Nummer erreichen:'
            ]
        ];
        if ($currentCat = $this->getCurrCategory()) {
            if ($currentCat->getCustomAttribute('cat_support_tel')) {
                $tel = $currentCat->getCustomAttribute('cat_support_tel')->getValue();
            } else {
                if (is_array($currentCat->getParentIds())) {
                    foreach (array_reverse($currentCat->getParentIds()) as $pcat) {
                        if ($parentEmail = $this->getCategoryById($pcat)->getCustomAttribute('cat_support_tel')) {
                            $tel = $parentEmail->getValue();
                            break;
                        }
                    }
                }
            }
        }
        return $tel;
    }
}
