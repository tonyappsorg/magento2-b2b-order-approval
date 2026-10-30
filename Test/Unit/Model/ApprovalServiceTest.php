<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\CartFingerprint;
use Tonyapps\B2bOrderApproval\Model\Notifier;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class ApprovalServiceTest extends TestCase
{
    private ApprovalRequest&MockObject $requests;
    private CartFingerprint&MockObject $fingerprint;
    private OrderApprovalPolicy&MockObject $policy;
    private CartRepositoryInterface&MockObject $cartRepository;
    private Json&MockObject $json;
    private Notifier&MockObject $notifier;
    private Quote&MockObject $quote;
    private ApprovalService $service;

    protected function setUp(): void
    {
        $this->requests = $this->createMock(ApprovalRequest::class);
        $this->fingerprint = $this->createMock(CartFingerprint::class);
        $this->policy = $this->createMock(OrderApprovalPolicy::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->json = $this->createMock(Json::class);
        $this->notifier = $this->createMock(Notifier::class);
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'hasItems', 'collectTotals'])
            ->getMock();
        $this->quote->setData([
            'entity_id' => 10,
            'customer_id' => 21,
            'store_id' => 1,
            'base_subtotal' => 120.0,
            'is_active' => 1,
        ]);
        $this->quote->method('hasItems')->willReturn(true);
        $this->quote->method('collectTotals')->willReturnCallback(
            fn() => $this->quote->setTotalsCollectedFlag(true)
        );
        $this->quote->method('getId')->willReturn(10);
        $this->service = new ApprovalService(
            $this->requests,
            $this->fingerprint,
            $this->policy,
            $this->cartRepository,
            $this->json,
            $this->notifier
        );
    }

    public function testSubmitStoresTheBuyerCompanyAndCartSnapshot(): void
    {
        $this->policy->method('requiresApproval')->with($this->quote)->willReturn(true);
        $this->policy->method('getCompanyId')->with($this->quote)->willReturn(5);
        $this->fingerprint->method('hash')->with($this->quote)->willReturn('fingerprint');
        $this->fingerprint->method('snapshot')->with($this->quote)->willReturn(['items' => []]);
        $this->json->method('serialize')->willReturn('{"items":[]}');
        $this->requests->expects(self::once())->method('create')
            ->with($this->quote, 5, 21, 'fingerprint', '{"items":[]}')->willReturn(31);

        self::assertSame(31, $this->service->submit($this->quote, 21));
    }

    public function testSubmitRefusesAQuoteOwnedByAnotherCustomer(): void
    {
        $this->policy->method('requiresApproval')->willReturn(true);
        $this->policy->method('getCompanyId')->willReturn(5);
        $this->requests->expects(self::never())->method('create');

        $this->expectException(LocalizedException::class);
        $this->service->submit($this->quote, 99);
    }

    public function testApproveAcceptsOnlyAnUnchangedCartFromTheSameCompanyBuyer(): void
    {
        $row = ['entity_id' => 31, 'quote_id' => 10, 'company_id' => 5, 'customer_id' => 21, 'cart_hash' => 'fingerprint', 'status' => ApprovalRequest::PENDING];
        $this->requests->method('getById')->with(31)->willReturn($row);
        $this->cartRepository->method('get')->with(10)->willReturn($this->quote);
        $this->policy->method('getCompanyId')->with($this->quote)->willReturn(5);
        $this->fingerprint->method('hash')->with($this->quote)->willReturn('fingerprint');
        $this->requests->expects(self::once())->method('approve')->with(31, 7, 'fingerprint')->willReturn(true);

        self::assertSame($row, $this->service->approve(31, 7, 5));
    }

    public function testApproveRejectsAChangedCart(): void
    {
        $row = ['entity_id' => 31, 'quote_id' => 10, 'company_id' => 5, 'customer_id' => 21, 'cart_hash' => 'old-fingerprint', 'status' => ApprovalRequest::PENDING];
        $this->requests->method('getById')->with(31)->willReturn($row);
        $this->cartRepository->method('get')->with(10)->willReturn($this->quote);
        $this->policy->method('getCompanyId')->with($this->quote)->willReturn(5);
        $this->fingerprint->method('hash')->with($this->quote)->willReturn('new-fingerprint');
        $this->requests->expects(self::once())->method('reject')->with(31, 7, 'Cart changed before approval.')->willReturn(true);
        $this->notifier->expects(self::once())->method('rejected')->with(31);

        $this->expectException(LocalizedException::class);
        $this->service->approve(31, 7, 5);
    }

    public function testOrderPlacementMarksTheApprovedRequestAsOrdered(): void
    {
        $this->requests->expects(self::once())->method('markOrdered')->with(31, 900);
        $this->service->markOrdered(31, 900);
    }

    public function testApprovedQuoteLookupDoesNotLeaveTotalsMarkedCollected(): void
    {
        $this->quote->setTotalsCollectedFlag(false);
        $this->requests->method('getApprovedForQuote')->with(10)->willReturn(
            ['entity_id' => 31, 'company_id' => 5, 'customer_id' => 21, 'cart_hash' => 'fingerprint', 'base_subtotal' => 120.0]
        );
        $this->policy->method('getCompanyId')->willReturn(5);
        $this->fingerprint->method('hash')->willReturn('fingerprint');

        self::assertSame(31, (int)$this->service->getApprovedForQuote($this->quote)['entity_id']);
        self::assertFalse((bool)$this->quote->getTotalsCollectedFlag());
    }

    public function testPlacementGateAllowsQuotesThatNeedNoApproval(): void
    {
        $this->policy->method('requiresApproval')->willReturn(false);
        $this->requests->expects(self::never())->method('getApprovedForQuote');

        self::assertNull($this->service->assertCanPlaceOrder($this->quote));
    }

    public function testPlacementGateRefusesAnUnapprovedCompanyOrder(): void
    {
        $this->policy->method('requiresApproval')->willReturn(true);
        $this->requests->method('getApprovedForQuote')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->service->assertCanPlaceOrder($this->quote);
    }

    public function testPlacementGateReturnsTheApprovalToConsume(): void
    {
        $this->policy->method('requiresApproval')->willReturn(true);
        $this->policy->method('getCompanyId')->willReturn(5);
        $this->fingerprint->method('hash')->willReturn('fingerprint');
        $this->requests->method('getApprovedForQuote')->willReturn(
            ['entity_id' => 31, 'company_id' => 5, 'customer_id' => 21, 'cart_hash' => 'fingerprint', 'base_subtotal' => 120.0]
        );

        self::assertSame(31, $this->service->assertCanPlaceOrder($this->quote));
    }

    public function testCheckoutStateIsLookedUpOncePerRequest(): void
    {
        $this->policy->expects(self::once())->method('requiresApproval')->willReturn(true);
        $this->requests->expects(self::once())->method('getApprovedForQuote')->willReturn(null);

        self::assertSame(ApprovalService::CHECKOUT_NEEDS_APPROVAL, $this->service->getCheckoutState($this->quote));
        self::assertSame(ApprovalService::CHECKOUT_NEEDS_APPROVAL, $this->service->getCheckoutState($this->quote));
    }
}
