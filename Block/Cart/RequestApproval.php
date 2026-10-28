<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;

class RequestApproval extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderApprovalPolicy $policy,
        private readonly ApprovalService $approvalService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canSubmit(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->hasItems() && $this->policy->requiresApproval($quote)
            && !$this->approvalService->hasApprovedRequest($quote);
    }

    public function hasApprovedRequest(): bool
    {
        return $this->approvalService->hasApprovedRequest($this->checkoutSession->getQuote());
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('b2borderapproval/cart/submit');
    }
}
