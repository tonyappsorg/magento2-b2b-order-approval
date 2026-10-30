<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
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
            $this->createMock(LoggerInterface::class)
        );

        $notifier->approved(7);
    }

    public function testRequestEmailGreetsTheAdministratorAndLeavesEscapingToTheTemplate(): void
    {
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->method('getById')->willReturn([
            'entity_id' => 7, 'company_id' => 5, 'store_id' => 1, 'requester_name' => "Pat O'Brien",
        ]);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getName')->willReturn('O\'Brien & Co');
        $company->method('getAdminCustomerId')->willReturn(3);
        $companies = $this->createMock(CompanyRepositoryInterface::class);
        $companies->method('getById')->with(5)->willReturn($company);
        $admin = $this->createMock(CustomerInterface::class);
        $admin->method('getEmail')->willReturn('admin@example.com');
        $admin->method('getFirstname')->willReturn('Ada');
        $admin->method('getLastname')->willReturn('Admin');
        $customers = $this->createMock(CustomerRepositoryInterface::class);
        $customers->method('getById')->with(3)->willReturn($admin);
        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://shop/approvals');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $vars = null;
        $builder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('setTemplateVars')->willReturnCallback(function (array $value) use (&$vars, $builder) {
            $vars = $value;
            return $builder;
        });
        $builder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        (new Notifier($requests, $companies, $customers, $builder, $storeManager, $this->createMock(LoggerInterface::class)))
            ->requestSubmitted(7);

        self::assertSame('Ada Admin', $vars['admin_name']);
        self::assertSame("O'Brien & Co", $vars['company_name']);
        self::assertSame("Pat O'Brien", $vars['buyer_name']);
    }
}
