<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;
use Tonyapps\B2bOrderApproval\Plugin\QuoteManagementPlugin;

class QuoteManagementPluginTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private OrderApprovalPolicy&MockObject $policy;
    private ApprovalService&MockObject $approvalService;
    private QuoteManagementPlugin $plugin;
    private Quote&MockObject $quote;
    private QuoteManagement&MockObject $subject;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->policy = $this->createMock(OrderApprovalPolicy::class);
        $this->approvalService = $this->createMock(ApprovalService::class);
        $this->plugin = new QuoteManagementPlugin($this->cartRepository, $this->policy, $this->approvalService);
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $this->subject = $this->getMockBuilder(QuoteManagement::class)->disableOriginalConstructor()->getMock();
        $this->cartRepository->method('get')->with(10)->willReturn($this->quote);
    }

    public function testOrdinaryOrBelowThresholdOrdersProceedWithoutApproval(): void
    {
        $this->policy->method('requiresApproval')->with($this->quote)->willReturn(false);
        $proceed = static fn($cartId, $paymentMethod) => 900;

        self::assertSame(900, $this->plugin->aroundPlaceOrder($this->subject, $proceed, 10, null));
    }

    public function testCompanyOrderWithoutApprovalIsBlocked(): void
    {
        $this->policy->method('requiresApproval')->with($this->quote)->willReturn(true);
        $this->approvalService->method('getApprovedForQuote')->with($this->quote)->willReturn(null);
        $proceed = static function () { self::fail('unapproved order must not be placed'); };

        $this->expectException(LocalizedException::class);
        $this->plugin->aroundPlaceOrder($this->subject, $proceed, 10, null);
    }

    public function testApprovedOrderProceedsAndConsumesTheApproval(): void
    {
        $this->policy->method('requiresApproval')->with($this->quote)->willReturn(true);
        $this->approvalService->method('getApprovedForQuote')->with($this->quote)
            ->willReturn(['entity_id' => 31]);
        $this->approvalService->expects(self::once())->method('markOrdered')->with(31, 900);
        $proceed = static fn($cartId, $paymentMethod) => 900;

        self::assertSame(900, $this->plugin->aroundPlaceOrder($this->subject, $proceed, 10, null));
    }
}
