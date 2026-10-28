<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Plugin;

use Magento\Checkout\Block\Onepage\Link;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;
use Tonyapps\B2bOrderApproval\Plugin\CheckoutLinkPlugin;

class CheckoutLinkPluginTest extends TestCase
{
    public function testHidesCheckoutUntilAnOrderIsApproved(): void
    {
        [$plugin, $quote, $policy, $approvalService] = $this->plugin();
        $policy->method('requiresApproval')->with($quote)->willReturn(true);
        $approvalService->method('hasApprovedRequest')->with($quote)->willReturn(false);

        self::assertFalse($plugin->afterIsPossibleOnepageCheckout($this->createMock(Link::class), true));
    }

    public function testRestoresCheckoutAfterApproval(): void
    {
        [$plugin, $quote, $policy, $approvalService] = $this->plugin();
        $policy->method('requiresApproval')->with($quote)->willReturn(true);
        $approvalService->method('hasApprovedRequest')->with($quote)->willReturn(true);

        self::assertTrue($plugin->afterIsPossibleOnepageCheckout($this->createMock(Link::class), true));
    }

    /** @return array{CheckoutLinkPlugin, Quote&MockObject, OrderApprovalPolicy&MockObject, ApprovalService&MockObject} */
    private function plugin(): array
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);
        $policy = $this->createMock(OrderApprovalPolicy::class);
        $approvalService = $this->createMock(ApprovalService::class);

        return [new CheckoutLinkPlugin($session, $policy, $approvalService), $quote, $policy, $approvalService];
    }
}
