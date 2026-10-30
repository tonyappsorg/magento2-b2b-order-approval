<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Plugin\QuoteManagementPlugin;

class QuoteManagementPluginTest extends TestCase
{
    private ApprovalService&MockObject $approvalService;
    private State&MockObject $appState;
    private QuoteManagementPlugin $plugin;
    private Quote&MockObject $quote;
    private QuoteManagement&MockObject $subject;
    private Order&MockObject $order;

    protected function setUp(): void
    {
        $this->approvalService = $this->createMock(ApprovalService::class);
        $this->appState = $this->createMock(State::class);
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_WEBAPI_REST);
        $this->plugin = new QuoteManagementPlugin($this->approvalService, $this->appState);
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $this->subject = $this->getMockBuilder(QuoteManagement::class)->disableOriginalConstructor()->getMock();
        $this->order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods(['getId'])->getMock();
        $this->order->method('getId')->willReturn(900);
    }

    public function testOrdinaryOrBelowThresholdOrdersProceedWithoutApproval(): void
    {
        $this->approvalService->method('assertCanPlaceOrder')->with($this->quote)->willReturn(null);
        $this->approvalService->expects(self::never())->method('markOrdered');

        self::assertSame($this->order, $this->plugin->aroundSubmit($this->subject, fn() => $this->order, $this->quote));
    }

    public function testCompanyOrderWithoutApprovalIsBlockedOnEverySubmitPath(): void
    {
        $this->approvalService->method('assertCanPlaceOrder')
            ->willThrowException(new LocalizedException(__('needs approval')));
        $proceed = static function () { self::fail('unapproved order must not be placed'); };

        $this->expectException(LocalizedException::class);
        $this->plugin->aroundSubmit($this->subject, $proceed, $this->quote);
    }

    public function testApprovedOrderProceedsAndConsumesTheApproval(): void
    {
        $this->approvalService->method('assertCanPlaceOrder')->willReturn(31);
        $this->approvalService->expects(self::once())->method('markOrdered')->with(31, 900);
        $proceed = function (Quote $quote, array $orderData) {
            self::assertSame(['k' => 'v'], $orderData);
            return $this->order;
        };

        self::assertSame($this->order, $this->plugin->aroundSubmit($this->subject, $proceed, $this->quote, ['k' => 'v']));
    }

    public function testAdminCreatedOrdersAreNotGated(): void
    {
        $appState = $this->createMock(State::class);
        $appState->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $this->approvalService->expects(self::never())->method('assertCanPlaceOrder');

        $plugin = new QuoteManagementPlugin($this->approvalService, $appState);
        self::assertSame($this->order, $plugin->aroundSubmit($this->subject, fn() => $this->order, $this->quote));
    }
}
