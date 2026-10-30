<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

/**
 * Approval gate on QuoteManagement::submit(), which placeOrder(), PayPal Express and the
 * PaymentServices smart buttons all go through. Multishipping is covered by observers.
 */
class QuoteManagementPlugin
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly State $appState
    ) {
    }

    public function aroundSubmit(QuoteManagement $subject, callable $proceed, Quote $quote, $orderData = [])
    {
        // Orders created by a store administrator in the admin panel are not buyer checkouts.
        if ($this->isAdminArea()) {
            return $proceed($quote, $orderData);
        }

        $requestId = $this->approvalService->assertCanPlaceOrder($quote);
        $order = $proceed($quote, $orderData);
        if ($requestId && $order && $order->getId()) {
            $this->approvalService->markOrdered($requestId, (int)$order->getId());
        }
        return $order;
    }

    private function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException) {
            return false;
        }
    }
}
