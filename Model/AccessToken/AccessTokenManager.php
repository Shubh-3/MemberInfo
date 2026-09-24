<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\AccessToken;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Grants and validates time-boxed, row-scoped access to sensitive member data
 * (Req. 9). A grant is bound to (admin_user_id, memberinfo_row_id) where
 * memberinfo_row_id is the order_item_id the admin verified their password
 * against -- so a grant for row 1 can never validate against row 2, even if
 * the request is tampered to swap the row_id.
 *
 * The database row IS the access boundary: Reveal checks (admin_user_id,
 * memberinfo_row_id, expires_at > now()) directly, scoped to the currently
 * logged-in admin's own session id -- never a client-supplied credential.
 * There is deliberately no bearer token the client must hold onto: a page
 * refresh (which clears any JS-memory cache) doesn't lose the grant, since
 * "is this still unlocked" is answered by the DB, not by browser state.
 */
class AccessTokenManager
{
    private const TABLE = 'vendor_memberinfo_access';
    private const TTL_SECONDS = 15 * 60;

    public function __construct(
        private ResourceConnection $resourceConnection,
        private DateTime $dateTime
    ) {
    }

    /**
     * @return string expires_at, in UTC ("Y-m-d H:i:s")
     */
    public function grant(int $adminUserId, int $memberInfoRowId): string
    {
        $expiresAt = $this->dateTime->gmtDate(null, time() + self::TTL_SECONDS);

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->insertOnDuplicate(
            $table,
            [
                'admin_user_id' => $adminUserId,
                'memberinfo_row_id' => $memberInfoRowId,
                'expires_at' => $expiresAt,
            ],
            ['expires_at']
        );

        return $expiresAt;
    }

    /**
     * Checks the DB directly for an unexpired grant on this exact
     * (admin, row) pair -- the only two identifiers that matter, and
     * neither is client-supplied: admin_user_id comes from the current
     * backend session, memberinfo_row_id from the row the admin clicked.
     * Never trust a client-side countdown as the actual boundary.
     */
    public function isValid(int $adminUserId, int $memberInfoRowId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table, ['expires_at'])
            ->where('admin_user_id = ?', $adminUserId)
            ->where('memberinfo_row_id = ?', $memberInfoRowId)
            ->where('expires_at > ?', $this->dateTime->gmtDate());

        return (bool) $connection->fetchOne($select);
    }

    public function purgeExpired(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->delete($table, ['expires_at < ?' => $this->dateTime->gmtDate()]);
    }
}
