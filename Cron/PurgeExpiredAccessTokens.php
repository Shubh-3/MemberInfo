<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Cron;

use Vendor\MemberInfo\Model\AccessToken\AccessTokenManager;

class PurgeExpiredAccessTokens
{
    public function __construct(
        private AccessTokenManager $accessTokenManager
    ) {
    }

    public function execute(): void
    {
        $this->accessTokenManager->purgeExpired();
    }
}
