<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Observer\EnforceMultishippingApproval;
use Tonyapps\B2bOrderApproval\Observer\MarkMultishippingApprovalOrdered;

class MultishippingApprovalTest extends TestCase
{
    public function testUnapprovedMultishippingCheckoutIsRefusedBeforeAnyOrderIsPlaced(): void
    {
        $service = $this->createMock(ApprovalService::class);
        $service->method('assertCanPlaceOrder')->willThrowException(new LocalizedException(__('needs approval')));

        $this->expectException(LocalizedException::class);
        (new EnforceMultishippingApproval($service))->execute($this->observer(['quote' => $this->quote()]));
    }

    public function testApprovalIsCheckedOnceAndConsumedByTheFirstPlacedOrder(): void
    {
        $quote = $this->quote();
        $service = $this->createMock(ApprovalService::class);
        $service->expects(self::once())->method('assertCanPlaceOrder')->with($quote)->willReturn(31);
        $gate = new EnforceMultishippingApproval($service);
        $gate->execute($this->observer(['quote' => $quote]));
        $gate->execute($this->observer(['quote' => $quote]));

        $service->expects(self::once())->method('markOrdered')->with(31, 900);
        (new MarkMultishippingApprovalOrdered($service))->execute($this->observer([
            'quote' => $quote,
            'orders' => [new DataObject(['id' => null]), new DataObject(['id' => 900]), new DataObject(['id' => 901])],
        ]));
    }

    public function testSingleAddressCheckoutIsLeftToTheSubmitPlugin(): void
    {
        $service = $this->createMock(ApprovalService::class);
        $service->expects(self::never())->method('markOrdered');

        (new MarkMultishippingApprovalOrdered($service))->execute($this->observer([
            'quote' => new DataObject(),
            'order' => new DataObject(['id' => 900]),
        ]));
    }

    private function quote(): Quote
    {
        return $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    /** @param array<string, mixed> $data */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }
}
