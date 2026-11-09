<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\CartFingerprint;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;
use Tonyapps\B2bOrderApproval\Observer\InvalidateChangedApproval;

class InvalidateChangedApprovalTest extends TestCase
{
    public function testSavedCustomerCartWithdrawsApprovalsForOtherContent(): void
    {
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->expects(self::once())->method('invalidateApproved')->with(10, 'current-hash');

        $this->observer($requests, 'current-hash')->execute($this->event($this->quote(10, 21, true)));
    }

    /** @dataProvider ignoredQuotes */
    public function testIgnoresGuestNewAndOrderedCarts(int $quoteId, ?int $customerId, bool $active): void
    {
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->expects(self::never())->method('invalidateApproved');

        $this->observer($requests, 'hash')->execute($this->event($this->quote($quoteId, $customerId, $active)));
    }

    public static function ignoredQuotes(): array
    {
        return [
            'guest' => [10, null, true],
            'not saved yet' => [0, 21, true],
            'placed (inactive)' => [10, 21, false],
        ];
    }

    private function observer(ApprovalRequest $requests, string $hash): InvalidateChangedApproval
    {
        $fingerprint = $this->createMock(CartFingerprint::class);
        $fingerprint->method('hash')->willReturn($hash);
        return new InvalidateChangedApproval($requests, $fingerprint);
    }

    private function quote(int $id, ?int $customerId, bool $active): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIsActive'])->addMethods(['getCustomerId'])->getMock();
        $quote->method('getId')->willReturn($id ?: null);
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->method('getIsActive')->willReturn($active ? 1 : 0);
        return $quote;
    }

    private function event(Quote $quote): Observer
    {
        return new Observer(['event' => new Event(['quote' => $quote])]);
    }
}
