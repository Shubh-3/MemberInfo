<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Exposes, per quote item, which member sub-forms the checkout step should
 * render (has_spouse/has_child). Deliberately does not return any previously
 * entered DOB/SSN: entered values live only in the browser's in-memory
 * ko.observables for the current session, never round-tripped back down from
 * the server as plaintext.
 */
class MemberInfoConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private CheckoutSession $checkoutSession
    ) {
    }

    public function getConfig(): array
    {
        $items = [];

        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $items[(int) $item->getItemId()] = [
                'has_spouse' => (bool) $item->getOptionByCode('member_option_spouse'),
                'has_child' => (bool) $item->getOptionByCode('member_option_child'),
                'product_name' => $item->getName(),
            ];
        }

        return [
            'memberInfoItems' => $items,
        ];
    }
}
