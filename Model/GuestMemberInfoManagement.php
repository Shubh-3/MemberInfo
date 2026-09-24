<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Vendor\MemberInfo\Api\GuestMemberInfoManagementInterface;
use Vendor\MemberInfo\Api\MemberInfoManagementInterface;

class GuestMemberInfoManagement implements GuestMemberInfoManagementInterface
{
    public function __construct(
        private MemberInfoManagementInterface $memberInfoManagement,
        private QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
    }

    public function saveForCartItem(string $cartId, int $itemId, array $members): bool
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $this->memberInfoManagement->saveForCartItem((int) $quoteIdMask->getQuoteId(), $itemId, $members);
    }
}
