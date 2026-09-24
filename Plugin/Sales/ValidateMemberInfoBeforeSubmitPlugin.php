<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Plugin\Sales;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote as QuoteEntity;
use Magento\Quote\Model\QuoteManagement;
use Vendor\MemberInfo\Api\Data\MemberInfoInterface;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoQuote;

/**
 * Server-side authoritative gate on order placement (Req. 4: "Validation must
 * be performed on both the frontend and backend"). Blocks the order if any
 * item is missing primary member data, or if the staged data for an item
 * contains a member type the item no longer has enabled -- a mismatch here
 * means the save-time filter in MemberInfoManagement was bypassed and is
 * defense-in-depth, not the primary control.
 */
class ValidateMemberInfoBeforeSubmitPlugin
{
    public function __construct(
        private MemberInfoQuote $memberInfoQuoteResource
    ) {
    }

    public function beforeSubmit(QuoteManagement $subject, QuoteEntity $quote, $orderData = []): array
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemId = (int) $item->getItemId();
            $staged = $this->memberInfoQuoteResource->getByQuoteItemId($itemId);

            if (!isset($staged[MemberInfoInterface::MEMBER_TYPE_PRIMARY])) {
                throw new LocalizedException(
                    __('Member information is required for "%1" before placing the order.', $item->getName())
                );
            }

            $hasSpouse = (bool) $item->getOptionByCode('member_option_spouse');
            $hasChild = (bool) $item->getOptionByCode('member_option_child');

            if (isset($staged[MemberInfoInterface::MEMBER_TYPE_SPOUSE]) && !$hasSpouse) {
                throw new LocalizedException(
                    __('Spouse information was submitted for "%1" but Spouse is not selected.', $item->getName())
                );
            }

            if (isset($staged[MemberInfoInterface::MEMBER_TYPE_CHILD]) && !$hasChild) {
                throw new LocalizedException(
                    __('Child information was submitted for "%1" but Child is not selected.', $item->getName())
                );
            }
        }

        return [$quote, $orderData];
    }
}
