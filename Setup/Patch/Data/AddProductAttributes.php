<?php
declare(strict_types=1);

namespace MagoArab\EasYorder\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddProductAttributes implements DataPatchInterface
{
    private $moduleDataSetup;
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
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // إضافة خاصية تفعيل النموذج
        $eavSetup->addAttribute(
            Product::ENTITY,
            'easyorder_enabled',
            [
                'type' => 'int',
                'label' => 'Enable EasyOrder Form',
                'input' => 'select',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'required' => false,
                'default' => '1',
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Product Details',
                'used_in_product_listing' => false,
                'visible_on_front' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_in_advanced_search' => false,
                'used_for_promo_rules' => false,
                'html_allowed_on_front' => false,
                'used_for_sort_by' => false
            ]
        );

        // خاصية اخفاء خيارت المنتج
        $eavSetup->addAttribute(
            Product::ENTITY,
            'easyorder_hide_region',
            [
                'type' => 'int',
                'label' => 'Hide Product Options in EasyOrder Form',
                'input' => 'select',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'required' => false,
                'default' => '0',  // تم تغيير القيمة من '1' إلى '0'
                'sort_order' => 101,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Product Details',
                'used_in_product_listing' => false,
                'visible_on_front' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_in_advanced_search' => false,
                'used_for_promo_rules' => false,
                'html_allowed_on_front' => false,
                'used_for_sort_by' => false
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