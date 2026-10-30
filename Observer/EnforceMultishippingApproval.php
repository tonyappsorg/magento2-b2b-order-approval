<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

/**
 * checkout_type_multishipping_create_orders_single: multishipping places orders without
 * QuoteManagement::submit(), so the gate runs here, before any order is placed.
 */
class EnforceMultishippingApproval implements ObserverInterface
{
    public const QUOTE_REQUEST_ID = 'tonyapps_b2b_approval_request_id';

    public function __construct(private readonly ApprovalService $approvalService)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || $quote->hasData(self::QUOTE_REQUEST_ID)) {
            return;
        }
        $quote->setData(self::QUOTE_REQUEST_ID, (int)$this->approvalService->assertCanPlaceOrder($quote));
    }
}
