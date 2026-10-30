<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Observer\RedirectCheckoutUntilApproved;

class RedirectCheckoutUntilApprovedTest extends TestCase
{
    public function testCheckoutRedirectsToCartWhileApprovalIsMissing(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects(self::once())->method('setRedirect')->with('https://shop/checkout/cart/');
        $actionFlag = $this->createMock(ActionFlag::class);
        $actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects(self::once())->method('addNoticeMessage');

        $this->observer(ApprovalService::CHECKOUT_NEEDS_APPROVAL, $actionFlag, $messages)
            ->execute($this->event($response));
    }

    public function testApprovedOrUngatedCheckoutIsLeftAlone(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects(self::never())->method('setRedirect');
        $actionFlag = $this->createMock(ActionFlag::class);
        $actionFlag->expects(self::never())->method('set');

        foreach ([ApprovalService::CHECKOUT_APPROVED, ApprovalService::CHECKOUT_OPEN] as $state) {
            $this->observer($state, $actionFlag, $this->createMock(ManagerInterface::class))
                ->execute($this->event($response));
        }
    }

    private function observer(string $state, ActionFlag $actionFlag, ManagerInterface $messages): RedirectCheckoutUntilApproved
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);
        $service = $this->createMock(ApprovalService::class);
        $service->method('getCheckoutState')->with($quote)->willReturn($state);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('checkout/cart')->willReturn('https://shop/checkout/cart/');

        return new RedirectCheckoutUntilApproved($session, $service, $actionFlag, $messages, $url);
    }

    private function event(HttpResponse $response): Observer
    {
        $action = $this->createMock(Action::class);
        $action->method('getResponse')->willReturn($response);

        return new Observer(['event' => new Event(['controller_action' => $action])]);
    }
}
