<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest as ApprovalRequestResource;

class ApprovalService
{
    public const CHECKOUT_OPEN = 'open';
    public const CHECKOUT_NEEDS_APPROVAL = 'needs_approval';
    public const CHECKOUT_APPROVED = 'approved';

    /** @var array<int, string> per-request storefront state, keyed by quote ID */
    private array $checkoutStates = [];

    public function __construct(
        private readonly ApprovalRequestResource $requests,
        private readonly CartFingerprint $fingerprint,
        private readonly OrderApprovalPolicy $policy,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Json $json,
        private readonly Notifier $notifier
    ) {
    }

    public function submit(Quote $quote, int $customerId): int
    {
        if (!$this->policy->requiresApproval($quote)) {
            throw new LocalizedException(__('This order does not require company approval.'));
        }
        $companyId = $this->policy->getCompanyId($quote);
        if (!$companyId || (int)$quote->getCustomerId() !== $customerId) {
            throw new LocalizedException(__('This cart cannot be submitted for approval.'));
        }
        if (!$quote->getId() || !$quote->hasItems()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
        $this->collectTotals($quote);
        return $this->requests->create(
            $quote,
            $companyId,
            $customerId,
            $this->fingerprint->hash($quote),
            $this->json->serialize($this->fingerprint->snapshot($quote))
        );
    }

    /** @return array<string, mixed> */
    public function approve(int $requestId, int $adminCustomerId, int $companyId): array
    {
        $request = $this->requests->getById($requestId);
        if (!$request || (int)$request['company_id'] !== $companyId) {
            throw new LocalizedException(__('The approval request could not be found.'));
        }
        if ($request['status'] !== ApprovalRequestResource::PENDING) {
            throw new LocalizedException(__('This request is no longer awaiting approval.'));
        }

        $quote = $this->loadQuote((int)$request['quote_id']);
        $this->collectTotals($quote);
        if (!$quote->getIsActive()
            || (int)$quote->getCustomerId() !== (int)$request['customer_id']
            || $this->policy->getCompanyId($quote) !== (int)$request['company_id']
            || $this->fingerprint->hash($quote) !== (string)$request['cart_hash']
        ) {
            if ($this->requests->reject($requestId, $adminCustomerId, 'Cart changed before approval.')) {
                $this->notifier->rejected($requestId);
            }
            throw new LocalizedException(__('The cart changed after the request was submitted. Ask the buyer to submit it again.'));
        }
        if (!$this->requests->approve($requestId, $adminCustomerId, (string)$request['cart_hash'])) {
            throw new LocalizedException(__('This request was updated by another user. Refresh and try again.'));
        }

        return $request;
    }

    public function reject(int $requestId, int $adminCustomerId, int $companyId, string $comment): void
    {
        $request = $this->requests->getById($requestId);
        if (!$request || (int)$request['company_id'] !== $companyId) {
            throw new LocalizedException(__('The approval request could not be found.'));
        }
        if (!$this->requests->reject($requestId, $adminCustomerId, mb_substr($comment, 0, 2000))) {
            throw new LocalizedException(__('This request is no longer awaiting approval.'));
        }
    }

    public function getApprovedForQuote(Quote $quote): ?array
    {
        $request = $this->requests->getApprovedForQuote((int)$quote->getId());
        if (!$request) {
            return null;
        }
        $this->collectTotals($quote);
        if (!$quote->getIsActive()
            || (int)$quote->getCustomerId() !== (int)$request['customer_id']
            || $this->policy->getCompanyId($quote) !== (int)$request['company_id']
            || $this->fingerprint->hash($quote) !== (string)$request['cart_hash']
            || (float)$quote->getBaseSubtotal() > (float)$request['base_subtotal'] + 0.0001
        ) {
            return null;
        }
        return $request;
    }

    /**
     * Storefront view of the cart: one of the CHECKOUT_* constants.
     *
     * Memoized per request so the cart page, checkout link, and checkout redirect share one lookup.
     * Order placement does not use this; it re-validates through assertCanPlaceOrder().
     */
    public function getCheckoutState(Quote $quote): string
    {
        $quoteId = (int)$quote->getId();
        if ($quoteId && isset($this->checkoutStates[$quoteId])) {
            return $this->checkoutStates[$quoteId];
        }
        $state = self::CHECKOUT_OPEN;
        if ($quote->hasItems() && $this->policy->requiresApproval($quote)) {
            $state = $this->getApprovedForQuote($quote) ? self::CHECKOUT_APPROVED : self::CHECKOUT_NEEDS_APPROVAL;
        }
        if ($quoteId) {
            $this->checkoutStates[$quoteId] = $state;
        }
        return $state;
    }

    /**
     * Gate for every quote-to-order path. Returns the approved request ID to consume, or null when
     * the quote needs no approval.
     */
    public function assertCanPlaceOrder(Quote $quote): ?int
    {
        if (!$this->policy->requiresApproval($quote)) {
            return null;
        }
        $request = $this->getApprovedForQuote($quote);
        if (!$request) {
            throw new LocalizedException(__('This company order needs approval. Submit the cart from the shopping cart page.'));
        }
        return (int)$request['entity_id'];
    }

    public function markOrdered(int $requestId, int $orderId): void
    {
        $this->requests->markOrdered($requestId, $orderId);
    }

    /**
     * Totals for the fingerprint, without leaving the "collected" flag set: later payment import
     * must still recalculate payment-dependent totals.
     */
    private function collectTotals(Quote $quote): void
    {
        $wasCollected = (bool)$quote->getTotalsCollectedFlag();
        $quote->collectTotals();
        if (!$wasCollected) {
            $quote->setTotalsCollectedFlag(false);
        }
    }

    private function loadQuote(int $quoteId): Quote
    {
        try {
            return $this->cartRepository->get($quoteId);
        } catch (\Exception) {
            throw new LocalizedException(__('The cart for this request no longer exists.'));
        }
    }
}
