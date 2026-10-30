<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Plugin;

use Magento\Checkout\Block\Onepage\Link;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Plugin\CheckoutLinkPlugin;

class CheckoutLinkPluginTest extends TestCase
{
    public function testHidesCheckoutUntilAnOrderIsApproved(): void
    {
        self::assertFalse($this->plugin(ApprovalService::CHECKOUT_NEEDS_APPROVAL)
            ->afterIsPossibleOnepageCheckout($this->createMock(Link::class), true));
    }

    public function testRestoresCheckoutAfterApproval(): void
    {
        self::assertTrue($this->plugin(ApprovalService::CHECKOUT_APPROVED)
            ->afterIsPossibleOnepageCheckout($this->createMock(Link::class), true));
    }

    private function plugin(string $state): CheckoutLinkPlugin
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);
        $approvalService = $this->createMock(ApprovalService::class);
        $approvalService->method('getCheckoutState')->with($quote)->willReturn($state);

        return new CheckoutLinkPlugin($session, $approvalService);
    }
}
