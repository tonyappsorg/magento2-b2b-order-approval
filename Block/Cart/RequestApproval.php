<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

class RequestApproval extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly ApprovalService $approvalService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canSubmit(): bool
    {
        return $this->getState() === ApprovalService::CHECKOUT_NEEDS_APPROVAL;
    }

    public function hasApprovedRequest(): bool
    {
        return $this->getState() === ApprovalService::CHECKOUT_APPROVED;
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('b2borderapproval/cart/submit');
    }

    private function getState(): string
    {
        return $this->approvalService->getCheckoutState($this->checkoutSession->getQuote());
    }
}
