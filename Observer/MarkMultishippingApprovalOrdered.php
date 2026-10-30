<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

/**
 * checkout_submit_all_after: consumes the approval checked by EnforceMultishippingApproval,
 * recording the first placed order of the multishipping checkout.
 */
class MarkMultishippingApprovalOrdered implements ObserverInterface
{
    public function __construct(private readonly ApprovalService $approvalService)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $requestId = $quote ? (int)$quote->getData(EnforceMultishippingApproval::QUOTE_REQUEST_ID) : 0;
        if (!$requestId) {
            return;
        }
        foreach ((array)$observer->getEvent()->getOrders() as $order) {
            if ($order->getId()) {
                $this->approvalService->markOrdered($requestId, (int)$order->getId());
                return;
            }
        }
    }
}
