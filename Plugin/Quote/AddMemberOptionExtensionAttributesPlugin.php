<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Plugin\Quote;

use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemExtensionFactory;

/**
 * Exposes whether Spouse/Child were selected for a cart item as extension attributes,
 * read by the checkout Member Information step to decide which sub-forms to render.
 */
class AddMemberOptionExtensionAttributesPlugin
{
    public function __construct(
        private CartItemExtensionFactory $extensionFactory
    ) {
    }

    public function afterGetItems($subject, array $items)
    {
        foreach ($items as $item) {
            $this->populate($item);
        }
        return $items;
    }

    public function afterGetItemById($subject, $result)
    {
        if ($result instanceof CartItemInterface) {
            $this->populate($result);
        }
        return $result;
    }

    private function populate(CartItemInterface $item): void
    {
        $extensionAttributes = $item->getExtensionAttributes() ?: $this->extensionFactory->create();

        $extensionAttributes->setHasSpouse((bool) $item->getOptionByCode('member_option_spouse'));
        $extensionAttributes->setHasChild((bool) $item->getOptionByCode('member_option_child'));

        $item->setExtensionAttributes($extensionAttributes);
    }
}
