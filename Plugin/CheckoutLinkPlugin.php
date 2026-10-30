<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Plugin;

use Magento\Checkout\Block\Onepage\Link;
use Magento\Checkout\Model\Session as CheckoutSession;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

class CheckoutLinkPlugin
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ApprovalService $approvalService
    ) {
    }

    public function afterIsPossibleOnepageCheckout(Link $subject, bool $result): bool
    {
        return $result && $this->approvalService->getCheckoutState($this->checkoutSession->getQuote())
            !== ApprovalService::CHECKOUT_NEEDS_APPROVAL;
    }
}
