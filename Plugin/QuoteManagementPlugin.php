<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;

class QuoteManagementPlugin
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderApprovalPolicy $policy,
        private readonly ApprovalService $approvalService
    ) {
    }

    public function aroundPlaceOrder(QuoteManagement $subject, callable $proceed, $cartId, $paymentMethod = null)
    {
        $quote = $this->cartRepository->get((int)$cartId);
        if (!$this->policy->requiresApproval($quote)) {
            return $proceed($cartId, $paymentMethod);
        }

        $request = $this->approvalService->getApprovedForQuote($quote);
        if (!$request) {
            throw new LocalizedException(__('This company order needs approval. Submit the cart from the shopping cart page.'));
        }

        $orderId = $proceed($cartId, $paymentMethod);
        $this->approvalService->markOrdered((int)$request['entity_id'], (int)$orderId);
        return $orderId;
    }
}
