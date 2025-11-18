<?php

declare(strict_types=1);

namespace DieMayrei\CategoryHero\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddHeroAttributes implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    private CategorySetupFactory $categorySetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $categorySetup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);

        if (!$categorySetup->getAttribute(Category::ENTITY, 'hero_is_active')) {
            $categorySetup->addAttribute(
                Category::ENTITY,
                'hero_is_active',
                [
                    'type' => 'int',
                    'label' => 'Hero aktivieren',
                    'input' => 'boolean',
                    'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                    'required' => false,
                    'sort_order' => 10,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Hero',
                    'default' => 0,
                    'visible' => true,
                    'user_defined' => true,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'is_wysiwyg_enabled' => false,
                ]
            );
        }

        if (!$categorySetup->getAttribute(Category::ENTITY, 'hero_image')) {
            $categorySetup->addAttribute(
                Category::ENTITY,
                'hero_image',
                [
                    'type' => 'varchar',
                    'label' => 'Hero Bild',
                    'input' => 'image',
                    'backend' => \Magento\Catalog\Model\Category\Attribute\Backend\Image::class,
                    'required' => false,
                    'sort_order' => 20,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Hero',
                    'visible' => true,
                    'user_defined' => true,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'is_wysiwyg_enabled' => false,
                ]
            );
        }

        $this->moduleDataSetup->endSetup();
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
