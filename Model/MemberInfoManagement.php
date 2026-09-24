<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Vendor\MemberInfo\Api\Data\MemberInfoInterface;
use Vendor\MemberInfo\Api\MemberInfoManagementInterface;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoQuote;
use Vendor\MemberInfo\Model\Validator\MemberInfoValidator;

class MemberInfoManagement implements MemberInfoManagementInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private MemberInfoQuote $memberInfoQuoteResource,
        private MemberInfoValidator $validator
    ) {
    }

    public function saveForCartItem(int $cartId, int $itemId, array $members): bool
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->get($cartId);

        $item = $quote->getItemById($itemId);
        if ($item === null) {
            throw new NoSuchEntityException(__('Cart item with ID "%1" does not exist in this cart.', $itemId));
        }

        $hasSpouse = (bool) $item->getOptionByCode('member_option_spouse');
        $hasChild = (bool) $item->getOptionByCode('member_option_child');

        $seenTypes = [];
        foreach ($members as $member) {
            $memberType = $member->getMemberType();
            $seenTypes[$memberType] = true;

            if ($memberType === MemberInfoInterface::MEMBER_TYPE_SPOUSE && !$hasSpouse) {
                // Server-side authoritative filter: an item without an active spouse
                // selection can never persist spouse data, regardless of client payload.
                continue;
            }

            if ($memberType === MemberInfoInterface::MEMBER_TYPE_CHILD && !$hasChild) {
                continue;
            }

            $this->validator->validate($member);

            $this->memberInfoQuoteResource->save((int) $quote->getId(), $itemId, $memberType, [
                'first_name' => $member->getFirstName(),
                'last_name' => $member->getLastName(),
                'dob' => $member->getDob(),
                'ssn' => $member->getSsn(),
            ]);
        }

        if (!isset($seenTypes[MemberInfoInterface::MEMBER_TYPE_PRIMARY])) {
            throw new LocalizedException(__('Primary member information is required.'));
        }

        // Clean up any previously staged spouse/child rows for options that are no
        // longer selected, so stale data can never be picked up at order placement.
        if (!$hasSpouse) {
            $this->memberInfoQuoteResource->deleteByQuoteItemAndType($itemId, MemberInfoInterface::MEMBER_TYPE_SPOUSE);
        }
        if (!$hasChild) {
            $this->memberInfoQuoteResource->deleteByQuoteItemAndType($itemId, MemberInfoInterface::MEMBER_TYPE_CHILD);
        }

        return true;
    }
}
