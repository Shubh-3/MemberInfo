<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Controller\Adminhtml\MemberInfo;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\MemberInfo\Api\MemberInfoRepositoryInterface;
use Vendor\MemberInfo\Model\AccessToken\AccessTokenManager;

/**
 * Returns decrypted member data for exactly one row (one order item's
 * primary/spouse/child bundle), gated on a still-unexpired grant in
 * vendor_memberinfo_access for (current admin, this row). No bearer token
 * changes hands -- the DB row itself is the access boundary, checked fresh
 * on every call against the session's own admin_user_id, never a
 * client-supplied one. Row-level security (Req. 9): a grant for row 1 can
 * never satisfy row 2, whether via URL edit, AJAX tampering, or replay,
 * because the row_id is part of the DB lookup key itself.
 */
class Reveal extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Vendor_MemberInfo::reveal_sensitive';

    public function __construct(
        Context $context,
        private BackendAuthSession $backendAuthSession,
        private AccessTokenManager $accessTokenManager,
        private MemberInfoRepositoryInterface $memberInfoRepository,
        private JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $rowId = (int) $this->getRequest()->getParam('row_id');
        $admin = $this->backendAuthSession->getUser();

        if ($rowId <= 0) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('Invalid request.'),
            ]);
        }

        if (!$this->accessTokenManager->isValid((int) $admin->getId(), $rowId)) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => __('Access expired or invalid. Please re-enter your password.'),
            ]);
        }

        $members = $this->memberInfoRepository->getDecryptedByOrderItemId($rowId);

        return $result->setData([
            'success' => true,
            'members' => $members,
        ]);
    }
}
