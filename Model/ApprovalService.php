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
    public function __construct(
        private readonly ApprovalRequestResource $requests,
        private readonly CartFingerprint $fingerprint,
        private readonly OrderApprovalPolicy $policy,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Json $json
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
        $quote->collectTotals();
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
        $quote->collectTotals();
        if (!$quote->getIsActive()
            || (int)$quote->getCustomerId() !== (int)$request['customer_id']
            || $this->policy->getCompanyId($quote) !== (int)$request['company_id']
            || $this->fingerprint->hash($quote) !== (string)$request['cart_hash']
        ) {
            $this->requests->reject($requestId, $adminCustomerId, 'Cart changed before approval.');
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
        $quote->collectTotals();
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

    public function hasApprovedRequest(Quote $quote): bool
    {
        return $this->getApprovedForQuote($quote) !== null;
    }

    public function markOrdered(int $requestId, int $orderId): void
    {
        $this->requests->markOrdered($requestId, $orderId);
    }

    /** @return array<string, mixed> */
    private function loadQuote(int $quoteId): Quote
    {
        try {
            return $this->cartRepository->get($quoteId);
        } catch (\Exception) {
            throw new LocalizedException(__('The cart for this request no longer exists.'));
        }
    }
}
