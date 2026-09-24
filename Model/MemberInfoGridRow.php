<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoGrid as MemberInfoGridResource;

/**
 * Read-only model backing one admin grid row. Never exposes decrypt()
 * -- the grid path only ever sees the encrypted columns, masked in
 * MemberInfoGridDataProvider before they reach the browser.
 */
class MemberInfoGridRow extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(MemberInfoGridResource::class);
    }
}
