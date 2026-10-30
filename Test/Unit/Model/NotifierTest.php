<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2bOrderApproval\Model\Notifier;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class NotifierTest extends TestCase
{
    public function testApprovedLooksUpRequestOnceAndReturnsWhenMissing(): void
    {
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->expects(self::once())->method('getById')->with(7)->willReturn(null);

        $notifier = new Notifier(
            $requests,
            $this->createMock(CompanyRepositoryInterface::class),
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(TransportBuilder::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Escaper::class),
            $this->createMock(LoggerInterface::class)
        );

        $notifier->approved(7);
    }
}
