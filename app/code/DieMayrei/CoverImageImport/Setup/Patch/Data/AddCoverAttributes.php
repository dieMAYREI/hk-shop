<?php
namespace Diemayrei\CoverImageImport\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCoverAttributes implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Product attribute: cover
        $eavSetup->addAttribute(
            Product::ENTITY,
            'cover',
            [
                'type' => 'text',
                'label' => 'Cover',
                'input' => 'select',
                'source' => \Diemayrei\CoverImageImport\Model\Category\Attribute\Source\Cover::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
            ]
        );

        // Category attribute: cover_category
        $eavSetup->addAttribute(
            Category::ENTITY,
            'cover_category',
            [
                'type' => 'text',
                'label' => 'Cover',
                'input' => 'select',
                'visible' => true,
                'required' => false,
                'source' => \Diemayrei\CoverImageImport\Model\Category\Attribute\Source\Cover::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'general',
            ]
        );

        // Category attributes: support tel/email and short description
        $eavSetup->addAttribute(
            Category::ENTITY,
            'cat_support_tel',
            [
                'group' => 'general',
                'label' => 'Support Telefonnummer',
                'type' => 'text',
                'input' => 'text',
                'required' => false,
                'used_in_product_listing' => true,
                'visible_on_front' => true,
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            ]
        );

        $eavSetup->addAttribute(
            Category::ENTITY,
            'cat_support_email',
            [
                'group' => 'general',
                'label' => 'Support E-Mail',
                'type' => 'text',
                'input' => 'text',
                'required' => false,
                'used_in_product_listing' => true,
                'visible_on_front' => true,
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            ]
        );

        $eavSetup->addAttribute(
            Category::ENTITY,
            'cat_short_description',
            [
                'type' => 'text',
                'label' => 'Kurzbeschreibung',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 40,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'wysiwyg_enabled' => true,
                'is_html_allowed_on_front' => true,
                'used_in_product_listing' => true,
                'visible_on_front' => true,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'group' => 'Content'
            ]
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
