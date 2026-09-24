<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Plugin\Quote;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * Persists the customer's product-page Spouse/Child checkbox selections onto the
 * quote item as options, the same mechanism Magento uses for custom options, so the
 * selection survives cart -> order without a separate table.
 */
class PersistMemberOptionsPlugin
{
    public function afterAddProduct(Quote $subject, $result, Product $product, $request = null)
    {
        if (!($result instanceof Item) || $request === null) {
            return $result;
        }

        $params = $this->extractParams($request);
        $selectedSpouse = !empty($params['member_options']['spouse']) && (bool) $product->getData('has_spouse');
        $selectedChild = !empty($params['member_options']['child']) && (bool) $product->getData('has_child');

        if ($selectedSpouse) {
            $result->addOption([
                'product_id' => $product->getId(),
                'code' => 'member_option_spouse',
                'value' => '1',
            ]);
        }

        if ($selectedChild) {
            $result->addOption([
                'product_id' => $product->getId(),
                'code' => 'member_option_child',
                'value' => '1',
            ]);
        }

        return $result;
    }

    private function extractParams($request): array
    {
        if ($request instanceof DataObject) {
            return $request->getData();
        }

        if (is_array($request)) {
            return $request;
        }

        return [];
    }
}
