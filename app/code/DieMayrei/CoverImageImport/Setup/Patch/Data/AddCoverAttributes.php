<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCoverAttributes implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $this->addProductCoverAttribute($eavSetup);
        $this->addCategoryCoverAttribute($eavSetup);
        $this->addCategorySupportAttributes($eavSetup);
        $this->addCategoryShortDescriptionAttribute($eavSetup);

        return $this;
    }

    private function addProductCoverAttribute(EavSetup $eavSetup): void
    {
        $eavSetup->addAttribute(
            Product::ENTITY,
            'cover',
            [
                'type' => 'text',
                'label' => 'Cover',
                'input' => 'select',
                'source' => \DieMayrei\CoverImageImport\Model\Category\Attribute\Source\Cover::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
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
    }

    private function addCategoryCoverAttribute(EavSetup $eavSetup): void
    {
        $eavSetup->addAttribute(
            Category::ENTITY,
            'cover_category',
            [
                'type' => 'text',
                'label' => 'Cover',
                'input' => 'select',
                'visible' => true,
                'required' => false,
                'source' => \DieMayrei\CoverImageImport\Model\Category\Attribute\Source\Cover::class,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'general',
            ]
        );
    }

    private function addCategorySupportAttributes(EavSetup $eavSetup): void
    {
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
                'global' => ScopedAttributeInterface::SCOPE_STORE,
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
                'global' => ScopedAttributeInterface::SCOPE_STORE,
            ]
        );
    }

    private function addCategoryShortDescriptionAttribute(EavSetup $eavSetup): void
    {
        $eavSetup->addAttribute(
            Category::ENTITY,
            'cat_short_description',
            [
                'type' => 'text',
                'label' => 'Kurzbeschreibung',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 40,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
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
