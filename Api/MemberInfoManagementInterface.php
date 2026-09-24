<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Api;

use Vendor\MemberInfo\Api\Data\MemberInfoInterface;

interface MemberInfoManagementInterface
{
    /**
     * Saves member information for a single cart item. The primary member is
     * always required; spouse/child entries are accepted only if the item still
     * has the corresponding option enabled at save time — this is the
     * authoritative, server-side enforcement that unselected member data is
     * never persisted or submitted with the order.
     *
     * @param int $cartId
     * @param int $itemId
     * @param \Vendor\MemberInfo\Api\Data\MemberInfoInterface[] $members
     * @return bool
     */
    public function saveForCartItem(int $cartId, int $itemId, array $members): bool;
}
