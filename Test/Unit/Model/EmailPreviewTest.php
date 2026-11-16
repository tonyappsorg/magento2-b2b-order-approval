<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2bOrderApproval\Model\EmailPreview;
use Tonyapps\B2bOrderApproval\Model\Notifier;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class EmailPreviewTest extends TestCase
{
    private const ROW = [
        'entity_id' => 7, 'company_id' => 5, 'store_id' => 1, 'requester_name' => 'Pat Buyer',
        'requester_email' => 'pat@acme.test', 'base_subtotal' => '118.0000', 'comment' => 'Too much',
        'cart_snapshot' => '{"items":[{"name":"Fusion Backpack","sku":"24-MB02","qty":"2","row_total":"118"}]}',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function emails(): array
    {
        return [
            'request' => ['tonyapps_b2b_order_approval_email_request_template', 'requestSubmitted'],
            'approved' => ['tonyapps_b2b_order_approval_email_approved_template', 'approved'],
            'rejected' => ['tonyapps_b2b_order_approval_email_rejected_template', 'rejected'],
        ];
    }

    /**
     * @dataProvider emails
     */
    public function testPreviewHasTheVariablesTheNotifierSendsAndChangesNothing(string $templateId, string $send): void
    {
        $sent = null;
        $this->notifier($this->capturingBuilder($sent), $this->requests())->$send(7);
        self::assertNotEmpty($sent['items']);

        $requests = $this->requests();
        foreach (['create', 'approve', 'reject', 'markOrdered', 'invalidateApproved'] as $write) {
            $requests->expects(self::never())->method($write);
        }
        $silent = $this->createMock(TransportBuilder::class);
        $silent->expects(self::never())->method('setTemplateVars');
        $silent->expects(self::never())->method('getTransport');

        $preview = new EmailPreview($requests, $this->notifier($silent, $requests));

        self::assertSame($sent, $preview->getVariables($templateId, '7'));
        // The request's store, which the notifier sends from.
        self::assertSame(1, $preview->getStoreId($templateId, '7'));
    }

    public function testRejectedEmailOffersRejectedRequestsAndFallsBackToAny(): void
    {
        $wheres = [];
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(function (...$args) use (&$wheres, $select) {
            $wheres[] = array_slice($args, 0, 2);
            return $select;
        });
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls([], [[
            'entity_id' => '7', 'company_name' => 'Acme', 'requester_name' => 'Pat Buyer',
            'created_at' => '2026-11-10 08:00:00',
        ]]);
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->method('getConnection')->willReturn($connection);

        $records = (new EmailPreview($requests, $this->createMock(Notifier::class)))
            ->getRecords('tonyapps_b2b_order_approval_email_rejected_template', 20);

        self::assertSame([['r.status IN (?)', ['rejected']]], $wheres);
        self::assertSame(['7' => '#7 · Acme · Pat Buyer · 2026-11-10'], $records);
    }

    private function requests(): ApprovalRequest
    {
        $requests = $this->createMock(ApprovalRequest::class);
        $requests->method('getById')->with(7)->willReturn(self::ROW);
        return $requests;
    }

    private function capturingBuilder(?array &$vars): TransportBuilder
    {
        $builder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('setTemplateVars')->willReturnCallback(function (array $value) use (&$vars, $builder) {
            $vars = $value;
            return $builder;
        });
        $builder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));
        return $builder;
    }

    private function notifier(TransportBuilder $builder, ApprovalRequest $requests): Notifier
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getName')->willReturn('Acme');
        $company->method('getAdminCustomerId')->willReturn(3);
        $companies = $this->createMock(CompanyRepositoryInterface::class);
        $companies->method('getById')->with(5)->willReturn($company);
        $admin = $this->createMock(CustomerInterface::class);
        $admin->method('getEmail')->willReturn('ada@acme.test');
        $admin->method('getFirstname')->willReturn('Ada');
        $admin->method('getLastname')->willReturn('Admin');
        $customers = $this->createMock(CustomerRepositoryInterface::class);
        $customers->method('getById')->with(3)->willReturn($admin);
        $currency = $this->createMock(Currency::class);
        $currency->method('format')->willReturnCallback(static fn(float $amount) => '$' . number_format($amount, 2));
        $store = $this->createMock(Store::class);
        $store->method('getBaseCurrency')->willReturn($currency);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('template');
        $frontendUrl = $this->createMock(Url::class);
        $frontendUrl->method('setScope')->willReturnSelf();
        $frontendUrl->method('getUrl')->willReturnArgument(0);

        return new Notifier(
            $requests,
            $companies,
            $customers,
            $builder,
            $storeManager,
            $this->createMock(LoggerInterface::class),
            $scopeConfig,
            $frontendUrl
        );
    }
}
