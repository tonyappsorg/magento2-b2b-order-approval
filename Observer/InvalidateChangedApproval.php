<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Tonyapps\B2bOrderApproval\Model\CartFingerprint;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

/**
 * An approval covers one cart. Once a saved quote stops matching it (lines changed, cart emptied),
 * the approval is withdrawn for good instead of coming back when the same lines are re-added.
 */
class InvalidateChangedApproval implements ObserverInterface
{
    public function __construct(
        private readonly ApprovalRequest $requests,
        private readonly CartFingerprint $fingerprint
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote || !$quote->getId() || !$quote->getCustomerId() || !$quote->getIsActive()) {
            return;
        }
        $this->requests->invalidateApproved((int)$quote->getId(), $this->fingerprint->hash($quote));
    }
}
