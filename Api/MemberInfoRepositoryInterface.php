<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Api;

/**
 * Internal-only decrypt access for the admin reveal flow. Never bound to a
 * webapi.xml route -- decrypted SSN/DOB must only ever leave this module
 * through the ACL + password + token-gated Reveal controller.
 */
interface MemberInfoRepositoryInterface
{
    /**
     * @param int $orderItemId One admin-grid row: primary + spouse + child bundle for a single order item.
     * @return array<string, array<string, string>> Decrypted fields keyed by member_type
     *      (primary/spouse/child), each an array of first_name, last_name, dob, ssn.
     *      Absent keys mean that member type was not selected for this item.
     */
    public function getDecryptedByOrderItemId(int $orderItemId): array;
}
