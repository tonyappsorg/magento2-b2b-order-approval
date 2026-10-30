<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class ApprovalRequestTest extends TestCase
{
    public function testCreateLocksTheQuoteRowBeforeLookingForAPendingRequest(): void
    {
        $calls = [];
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->expects(self::once())->method('forUpdate')->with(true)->willReturnCallback(
            function () use (&$calls, $select) {
                $calls[] = 'lock';
                return $select;
            }
        );
        $connection = $this->createMock(Mysql::class);
        $connection->method('select')->willReturn($select);
        $connection->method('beginTransaction')->willReturnCallback(function () use (&$calls, $connection) {
            $calls[] = 'begin';
            return $connection;
        });
        $connection->method('fetchRow')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'find-pending';
            return false;
        });
        $connection->method('insert')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'insert';
            return 1;
        });
        $connection->method('lastInsertId')->willReturn('31');
        $connection->method('commit')->willReturnCallback(function () use (&$calls, $connection) {
            $calls[] = 'commit';
            return $connection;
        });
        $resource = $this->getMockBuilder(ApprovalRequest::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable', 'getTable'])->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('tonyapps_b2b_order_approval');
        $resource->method('getTable')->with('quote')->willReturn('quote');
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods(['getId', 'getStoreId'])->getMock();
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getId')->willReturn(10);

        self::assertSame(31, $resource->create($quote, 5, 21, 'hash', '{}'));
        self::assertSame(['begin', 'lock', 'find-pending', 'insert', 'commit'], $calls);
    }
}
