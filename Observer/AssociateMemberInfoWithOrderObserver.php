<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoOrder;
use Vendor\MemberInfo\Model\ResourceModel\MemberInfoQuote;

/**
 * Migrates staged, quote-item-keyed member data into the order-item-keyed
 * table once the order has been fully placed and saved (checkout_submit_all_after,
 * not sales_order_place_after -- see etc/events.xml for why), using the order
 * item's own getQuoteItemId() as the join key. This is what guarantees Req. 6:
 * each product/order item keeps its own member data, with no cross-contamination
 * even when an order has multiple products.
 */
class AssociateMemberInfoWithOrderObserver implements ObserverInterface
{
    public function __construct(
        private MemberInfoQuote $memberInfoQuoteResource,
        private MemberInfoOrder $memberInfoOrderResource
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $orderId = (int) $order->getEntityId();

        foreach ($order->getAllItems() as $orderItem) {
            $quoteItemId = (int) $orderItem->getQuoteItemId();
            if ($quoteItemId === 0) {
                continue;
            }

            $staged = $this->memberInfoQuoteResource->getByQuoteItemId($quoteItemId);
            if (empty($staged)) {
                continue;
            }

            $orderItemId = (int) $orderItem->getItemId();
            foreach ($staged as $memberType => $row) {
                $this->memberInfoOrderResource->insert($orderId, $orderItemId, $memberType, $row);
            }

            $this->memberInfoQuoteResource->deleteByQuoteItemId($quoteItemId);
        }
    }
}
