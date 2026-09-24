<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Vendor\MemberInfo\Api\MemberInfoRepositoryInterface;
use Vendor\MemberInfo\Model\Encryptor\MemberDataEncryptor;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoOrder;

class MemberInfoRepository implements MemberInfoRepositoryInterface
{
    public function __construct(
        private MemberInfoOrder $memberInfoOrder,
        private MemberDataEncryptor $encryptor
    ) {
    }

    public function getDecryptedByOrderItemId(int $orderItemId): array
    {
        $rows = $this->memberInfoOrder->getByOrderItemId($orderItemId);

        $result = [];
        foreach ($rows as $memberType => $row) {
            $result[$memberType] = [
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'dob' => $this->encryptor->decrypt((string) $row['dob_encrypted']),
                'ssn' => $this->encryptor->decrypt((string) $row['ssn_encrypted']),
            ];
        }

        return $result;
    }
}
