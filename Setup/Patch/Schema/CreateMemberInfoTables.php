<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class CreateMemberInfoTables implements SchemaPatchInterface
{
    public function __construct(
        private SchemaSetupInterface $schemaSetup
    ) {
    }

    public function apply(): void
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();
        $connection = $setup->getConnection();

        $this->createQuoteStagingTable($setup, $connection);
        $this->createOrderTable($setup, $connection);
        $this->createAccessTokenTable($setup, $connection);

        $setup->endSetup();
    }

    private function createQuoteStagingTable(SchemaSetupInterface $setup, $connection): void
    {
        $tableName = $setup->getTable('vendor_memberinfo_quote');
        if ($connection->isTableExists($tableName)) {
            return;
        }

        $table = $connection->newTable($tableName)
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity ID'
            )
            ->addColumn('quote_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false], 'Quote ID')
            ->addColumn(
                'quote_item_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Quote Item ID'
            )
            ->addColumn(
                'member_type',
                Table::TYPE_TEXT,
                10,
                ['nullable' => false],
                'Member Type: primary, spouse, child'
            )
            ->addColumn('first_name', Table::TYPE_TEXT, 255, ['nullable' => false], 'First Name')
            ->addColumn('last_name', Table::TYPE_TEXT, 255, ['nullable' => false], 'Last Name')
            ->addColumn('dob_encrypted', Table::TYPE_TEXT, '2M', ['nullable' => false], 'Encrypted DOB')
            ->addColumn('ssn_encrypted', Table::TYPE_TEXT, '2M', ['nullable' => false], 'Encrypted SSN')
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addIndex(
                $setup->getIdxName($tableName, ['quote_item_id', 'member_type'], \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
                ['quote_item_id', 'member_type'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex($setup->getIdxName($tableName, ['quote_id']), ['quote_id'])
            ->setComment('Member Info staged against quote items during checkout');

        $connection->createTable($table);
    }

    private function createOrderTable(SchemaSetupInterface $setup, $connection): void
    {
        $tableName = $setup->getTable('vendor_memberinfo');
        if ($connection->isTableExists($tableName)) {
            return;
        }

        $table = $connection->newTable($tableName)
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity ID'
            )
            ->addColumn(
                'order_item_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Order Item ID'
            )
            ->addColumn('order_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false], 'Order ID')
            ->addColumn(
                'member_type',
                Table::TYPE_TEXT,
                10,
                ['nullable' => false],
                'Member Type: primary, spouse, child'
            )
            ->addColumn('first_name', Table::TYPE_TEXT, 255, ['nullable' => false], 'First Name')
            ->addColumn('last_name', Table::TYPE_TEXT, 255, ['nullable' => false], 'Last Name')
            ->addColumn('dob_encrypted', Table::TYPE_TEXT, '2M', ['nullable' => false], 'Encrypted DOB')
            ->addColumn('ssn_encrypted', Table::TYPE_TEXT, '2M', ['nullable' => false], 'Encrypted SSN')
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addIndex(
                $setup->getIdxName($tableName, ['order_item_id', 'member_type'], \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
                ['order_item_id', 'member_type'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex($setup->getIdxName($tableName, ['order_id']), ['order_id'])
            ->setComment('Member Info associated with placed order items');

        $connection->createTable($table);
    }

    private function createAccessTokenTable(SchemaSetupInterface $setup, $connection): void
    {
        $tableName = $setup->getTable('vendor_memberinfo_access');
        if ($connection->isTableExists($tableName)) {
            return;
        }

        $table = $connection->newTable($tableName)
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity ID'
            )
            ->addColumn(
                'admin_user_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Admin User ID'
            )
            ->addColumn(
                'memberinfo_row_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Member Info Row ID (vendor_memberinfo.entity_id)'
            )
            ->addColumn(
                'token_hash',
                Table::TYPE_TEXT,
                64,
                ['nullable' => false],
                'SHA-256 hash of the granted access token'
            )
            ->addColumn('expires_at', Table::TYPE_DATETIME, null, ['nullable' => false], 'Expires At')
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addIndex(
                $setup->getIdxName(
                    $tableName,
                    ['admin_user_id', 'memberinfo_row_id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['admin_user_id', 'memberinfo_row_id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex($setup->getIdxName($tableName, ['expires_at']), ['expires_at'])
            ->setComment('Time-boxed, row-scoped grants for revealing sensitive member data');

        $connection->createTable($table);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
