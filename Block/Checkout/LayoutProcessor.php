<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Block\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

/**
 * Injects the "Member Information" step between Shipping and Payment/Review
 * into the checkout jsLayout tree, wired via Magento\Checkout\Block\Onepage's
 * layoutProcessors argument (etc/frontend/di.xml).
 *
 * NOTE: the UI library's "sortOrder" for step-level children is not a
 * numeric sort key -- Magento\Ui\..\collection.js._insertAt() treats a
 * numeric sortOrder as a literal array-splice index, so a value like 1.5
 * (to sit "between" shipping=1 and payment=2) creates a stray non-integer
 * array property instead of inserting in visual order. The supported way
 * to position a step relative to a sibling is the {after: <fully
 * qualified name>} form, which does a name lookup instead of an index
 * guess -- hence 'checkout.steps.shipping-step' below, not just
 * 'shipping-step'.
 */
class LayoutProcessor implements LayoutProcessorInterface
{
    public function process($jsLayout)
    {
        $stepsPath = ['components', 'checkout', 'children', 'steps', 'children'];

        if (!$this->pathExists($jsLayout, $stepsPath)) {
            return $jsLayout;
        }

        $steps = &$this->getReferenceByPath($jsLayout, $stepsPath);

        $steps['member-information-step'] = [
            'component' => 'uiComponent',
            'sortOrder' => ['after' => 'checkout.steps.shipping-step'],
            'children' => [
                'member-information' => [
                    'component' => 'Vendor_MemberInfo/js/view/member-information',
                    'sortOrder' => 10,
                ],
            ],
        ];

        return $jsLayout;
    }

    private function pathExists(array $data, array $path): bool
    {
        foreach ($path as $key) {
            if (!isset($data[$key])) {
                return false;
            }
            $data = $data[$key];
        }
        return true;
    }

    private function &getReferenceByPath(array &$data, array $path)
    {
        $ref = &$data;
        foreach ($path as $key) {
            $ref = &$ref[$key];
        }
        return $ref;
    }
}
