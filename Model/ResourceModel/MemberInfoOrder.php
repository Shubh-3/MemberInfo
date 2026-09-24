<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Table gateway for the order-item-scoped member info table. Rows here are
 * immutable snapshots created once at order placement (see the
 * sales_order_place_after observer) from the quote-staging table.
 *
 * A "row" in admin-grid/reveal terms is one order item's full bundle
 * (primary + spouse + child), so lookups here are always keyed by
 * order_item_id, matching one grid row and one access-token grant.
 */
class MemberInfoOrder
{
    private const TABLE = 'vendor_memberinfo';

    public function __construct(
        private ResourceConnection $resourceConnection
    ) {
    }

    public function insert(int $orderId, int $orderItemId, string $memberType, array $encryptedRow): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->insertOnDuplicate(
            $table,
            [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'member_type' => $memberType,
                'first_name' => $encryptedRow['first_name'],
                'last_name' => $encryptedRow['last_name'],
                'dob_encrypted' => $encryptedRow['dob_encrypted'],
                'ssn_encrypted' => $encryptedRow['ssn_encrypted'],
            ],
            ['first_name', 'last_name', 'dob_encrypted', 'ssn_encrypted']
        );
    }

    /**
     * @return array<string, array<string, mixed>> Rows keyed by member_type (still encrypted).
     */
    public function getByOrderItemId(int $orderItemId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()->from($table)->where('order_item_id = ?', $orderItemId);
        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['member_type']] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>> Rows for a given order (all member types, all items).
     */
    public function getByOrderId(int $orderId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()->from($table)->where('order_id = ?', $orderId);

        return $connection->fetchAll($select);
    }
}
