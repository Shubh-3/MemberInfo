<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Api\Data;

/**
 * DTO for a single member's data as submitted from checkout.
 * Carries plaintext in memory only for the duration of the request;
 * encryption happens in the repository at persistence time.
 *
 * NOTE: Magento's Web API layer (ServiceInputProcessor/TypeProcessor)
 * resolves field types via reflection on @return/@param docblock
 * annotations, not native PHP type hints -- without them, incoming
 * snake_case fields like "member_type" fail to map to setMemberType()
 * and are rejected as unsupported.
 */
interface MemberInfoInterface
{
    public const MEMBER_TYPE_PRIMARY = 'primary';
    public const MEMBER_TYPE_SPOUSE = 'spouse';
    public const MEMBER_TYPE_CHILD = 'child';

    /**
     * @return string
     */
    public function getMemberType(): string;

    /**
     * @param string $memberType
     * @return $this
     */
    public function setMemberType(string $memberType): self;

    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @param string $firstName
     * @return $this
     */
    public function setFirstName(string $firstName): self;

    /**
     * @return string
     */
    public function getLastName(): string;

    /**
     * @param string $lastName
     * @return $this
     */
    public function setLastName(string $lastName): self;

    /**
     * @return string
     */
    public function getDob(): string;

    /**
     * @param string $dob
     * @return $this
     */
    public function setDob(string $dob): self;

    /**
     * @return string
     */
    public function getSsn(): string;

    /**
     * @param string $ssn
     * @return $this
     */
    public function setSsn(string $ssn): self;
}
