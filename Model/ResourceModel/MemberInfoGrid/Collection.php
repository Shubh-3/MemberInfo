<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\ResourceModel\MemberInfoGrid;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface as Logger;

/**
 * One row per order item, pivoting the primary/spouse/child rows in
 * vendor_memberinfo into columns (self-joined three times on order_item_id +
 * member_type) so the grid matches the spec's single-row-per-order-item
 * layout. Only encrypted dob_encrypted/ssn_encrypted columns are selected --
 * decryption never happens on this read path (see MemberInfoGridDataProvider,
 * which applies masking before anything reaches the browser).
 */
class Collection extends AbstractCollection
{
    protected $_idFieldName = 'order_item_id';
    protected $_eventPrefix = 'vendor_memberinfo_grid_collection';
    protected $_eventObject = 'memberinfo_grid_collection';

    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private ResourceConnection $resourceConnection,
        $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    protected function _construct(): void
    {
        $this->_init(
            \Vendor\MemberInfo\Model\MemberInfoGridRow::class,
            \Vendor\MemberInfo\Model\ResourceModel\MemberInfoGrid::class
        );
    }

    protected function _initSelect()
    {
        parent::_initSelect();

        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $this->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->where('main_table.member_type = ?', 'primary')
            ->columns([
                'entity_id' => 'main_table.order_item_id',
                'order_item_id' => 'main_table.order_item_id',
                'order_id' => 'main_table.order_id',
                'member_ssn_encrypted' => 'main_table.ssn_encrypted',
            ])
            ->joinLeft(
                ['order' => $orderTable],
                'main_table.order_id = order.entity_id',
                ['order_increment_id' => 'order.increment_id']
            )
            ->joinLeft(
                ['spouse' => $this->resolveTableName('vendor_memberinfo')],
                'spouse.order_item_id = main_table.order_item_id AND spouse.member_type = \'spouse\'',
                [
                    'spouse_first_name' => 'spouse.first_name',
                    'spouse_last_name' => 'spouse.last_name',
                    'spouse_dob_encrypted' => 'spouse.dob_encrypted',
                    'spouse_ssn_encrypted' => 'spouse.ssn_encrypted',
                ]
            )
            ->joinLeft(
                ['child' => $this->resolveTableName('vendor_memberinfo')],
                'child.order_item_id = main_table.order_item_id AND child.member_type = \'child\'',
                [
                    'child_first_name' => 'child.first_name',
                    'child_last_name' => 'child.last_name',
                    'child_dob_encrypted' => 'child.dob_encrypted',
                    'child_ssn_encrypted' => 'child.ssn_encrypted',
                ]
            );

        return $this;
    }

    private function resolveTableName(string $table): string
    {
        return $this->resourceConnection->getTableName($table);
    }
}
