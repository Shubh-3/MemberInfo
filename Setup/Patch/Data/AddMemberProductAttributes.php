<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddMemberProductAttributes implements DataPatchInterface
{
    private const ATTRIBUTES = [
        'has_spouse' => 'Has Spouse',
        'has_child' => 'Has Child',
    ];

    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::ATTRIBUTES as $code => $label) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => 'int',
                'label' => $label,
                'input' => 'boolean',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => 0,
                'group' => 'General',
                'used_in_product_listing' => true,
            ]);
        }

        $this->moduleDataSetup->getConnection()->endSetup();
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
