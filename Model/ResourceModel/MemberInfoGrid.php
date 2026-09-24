<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MemberInfoGrid extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('vendor_memberinfo', 'entity_id');
    }
}
