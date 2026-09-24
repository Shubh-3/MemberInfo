<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Magento\Framework\DataObject;
use Vendor\MemberInfo\Api\Data\MemberInfoInterface;

class MemberInfo extends DataObject implements MemberInfoInterface
{
    public function getMemberType(): string
    {
        return (string) $this->getData('member_type');
    }

    public function setMemberType(string $memberType): self
    {
        return $this->setData('member_type', $memberType);
    }

    public function getFirstName(): string
    {
        return (string) $this->getData('first_name');
    }

    public function setFirstName(string $firstName): self
    {
        return $this->setData('first_name', $firstName);
    }

    public function getLastName(): string
    {
        return (string) $this->getData('last_name');
    }

    public function setLastName(string $lastName): self
    {
        return $this->setData('last_name', $lastName);
    }

    public function getDob(): string
    {
        return (string) $this->getData('dob');
    }

    public function setDob(string $dob): self
    {
        return $this->setData('dob', $dob);
    }

    public function getSsn(): string
    {
        return (string) $this->getData('ssn');
    }

    public function setSsn(string $ssn): self
    {
        return $this->setData('ssn', $ssn);
    }
}
