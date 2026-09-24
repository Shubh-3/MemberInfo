<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoGrid\CollectionFactory;

/**
 * Feeds the admin grid. Masks Member/Spouse/Child SSN and Spouse/Child DOB by
 * default (Req. 7) -- the mask is computed here in PHP from the still-encrypted
 * column, so the real value is never serialized into the grid response. Names
 * and Order Increment ID pass through unmasked, as specified.
 */
class MemberInfoGridDataProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData(): array
    {
        $items = [];

        foreach ($this->getCollection()->getItems() as $item) {
            $row = $item->getData();

            $row['member_ssn'] = $this->maskPresence($row['member_ssn_encrypted'] ?? null);
            $row['spouse_ssn'] = $this->maskPresence($row['spouse_ssn_encrypted'] ?? null);
            $row['spouse_dob'] = $this->maskPresence($row['spouse_dob_encrypted'] ?? null);
            $row['child_ssn'] = $this->maskPresence($row['child_ssn_encrypted'] ?? null);
            $row['child_dob'] = $this->maskPresence($row['child_dob_encrypted'] ?? null);

            unset(
                $row['member_ssn_encrypted'],
                $row['spouse_ssn_encrypted'],
                $row['spouse_dob_encrypted'],
                $row['child_ssn_encrypted'],
                $row['child_dob_encrypted']
            );

            $items[] = $row;
        }

        return [
            'items' => $items,
            'totalRecords' => $this->getCollection()->getSize(),
        ];
    }

    private function maskPresence(?string $encryptedValue): string
    {
        return $encryptedValue ? '••••••' : '';
    }
}
