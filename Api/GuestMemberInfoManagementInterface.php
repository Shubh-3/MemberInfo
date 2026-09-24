<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Api;

/**
 * Guest-checkout counterpart to MemberInfoManagementInterface. Takes the
 * masked cart ID exposed to unauthenticated storefront requests and
 * resolves it to the real quote ID server-side before delegating -- the
 * same split Magento itself uses between CartItemRepositoryInterface and
 * GuestCartItemRepositoryInterface.
 */
interface GuestMemberInfoManagementInterface
{
    /**
     * @param string $cartId Masked quote ID.
     * @param int $itemId
     * @param \Vendor\MemberInfo\Api\Data\MemberInfoInterface[] $members
     * @return bool
     */
    public function saveForCartItem(string $cartId, int $itemId, array $members): bool;
}
