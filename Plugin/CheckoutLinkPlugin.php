<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Plugin;

use Magento\Checkout\Block\Onepage\Link;
use Magento\Checkout\Model\Session as CheckoutSession;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;

class CheckoutLinkPlugin
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderApprovalPolicy $policy,
        private readonly ApprovalService $approvalService
    ) {
    }

    public function afterIsPossibleOnepageCheckout(Link $subject, bool $result): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $result && (!$this->policy->requiresApproval($quote) || $this->approvalService->hasApprovedRequest($quote));
    }
}
