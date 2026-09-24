<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Vendor\MemberInfo\Model\Encryptor\MemberDataEncryptor;

/**
 * Direct table gateway for the quote-staging table. Kept table-gateway style
 * (rather than an AbstractDb resource model bound to a DataObject) because rows
 * are always written/read as encrypted blobs keyed by (quote_item_id, member_type),
 * never loaded generically by primary key from the UI.
 */
class MemberInfoQuote
{
    private const TABLE = 'vendor_memberinfo_quote';

    public function __construct(
        private ResourceConnection $resourceConnection,
        private MemberDataEncryptor $encryptor
    ) {
    }

    /**
     * Replaces the row for (quoteItemId, memberType) with fresh encrypted data.
     */
    public function save(int $quoteId, int $quoteItemId, string $memberType, array $data): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $row = [
            'quote_id' => $quoteId,
            'quote_item_id' => $quoteItemId,
            'member_type' => $memberType,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'dob_encrypted' => $this->encryptor->encrypt($data['dob']),
            'ssn_encrypted' => $this->encryptor->encrypt($data['ssn']),
        ];

        $connection->insertOnDuplicate($table, $row, [
            'first_name',
            'last_name',
            'dob_encrypted',
            'ssn_encrypted',
        ]);
    }

    /**
     * Deletes the row for a member type that is no longer selected, so stale
     * data can never be picked up at order-placement time.
     */
    public function deleteByQuoteItemAndType(int $quoteItemId, string $memberType): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->delete($table, [
            'quote_item_id = ?' => $quoteItemId,
            'member_type = ?' => $memberType,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>> Rows keyed by member_type, first/last plaintext,
     *      dob/ssn still encrypted (caller decrypts only where authorized).
     */
    public function getByQuoteItemId(int $quoteItemId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table)
            ->where('quote_item_id = ?', $quoteItemId);

        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['member_type']] = $row;
        }

        return $result;
    }

    public function deleteByQuoteItemId(int $quoteItemId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->delete($table, ['quote_item_id = ?' => $quoteItemId]);
    }
}
