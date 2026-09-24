<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Controller\Adminhtml\MemberInfo;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\AuthenticationException;
use Vendor\MemberInfo\Model\AccessToken\AccessTokenManager;

/**
 * Re-verifies the CURRENTLY LOGGED-IN admin's own password before any
 * sensitive member data can be revealed (Req. 8). The username is always
 * taken from the session, never from the request, so an admin can't
 * "verify" using someone else's credential context.
 *
 * Calls verifyIdentity() directly on the User model already loaded in the
 * backend session -- a hash comparison plus active/role checks, with no
 * event dispatch. Two other paths were tried and rejected:
 *   - Backend\Model\Auth::login() runs the full sign-in flow; on success it
 *     calls Session::processLogin(), which regenerates the session ID and
 *     form_key as if a brand new login just happened.
 *   - User::authenticate() doesn't touch the session, but internally
 *     dispatches 'admin_user_authenticate_after', which
 *     Magento_PageCache's FlushFormKey observer listens for and uses to
 *     null out the current form_key -- so the very next POST (the Reveal
 *     call) fails CSRF validation even though this request succeeds.
 * verifyIdentity() has neither side effect.
 */
class VerifyPassword extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Vendor_MemberInfo::reveal_sensitive';

    public function __construct(
        Context $context,
        private BackendAuthSession $backendAuthSession,
        private AccessTokenManager $accessTokenManager,
        private JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $password = (string) $this->getRequest()->getParam('password', '');
        $rowId = (int) $this->getRequest()->getParam('row_id');

        if ($rowId <= 0 || $password === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('Password and row ID are required.'),
            ]);
        }

        $admin = $this->backendAuthSession->getUser();

        try {
            $isValid = $admin->verifyIdentity($password);
        } catch (AuthenticationException) {
            $isValid = false;
        }

        if (!$isValid) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'message' => __('Incorrect password.'),
            ]);
        }

        $expiresAt = $this->accessTokenManager->grant((int) $admin->getId(), $rowId);

        return $result->setData([
            'success' => true,
            'expires_at' => $expiresAt,
        ]);
    }
}
