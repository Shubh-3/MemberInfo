<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Controller\Adminhtml\MemberInfo;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Vendor_MemberInfo::view';

    public function __construct(
        Context $context,
        private PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_MemberInfo::memberinfo');
        $resultPage->getConfig()->getTitle()->prepend(__('Member Information'));

        return $resultPage;
    }
}
