<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Block\Approval;

use Magento\Directory\Model\Currency;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bOrderApproval\Block\Approval\Requests;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class RequestsTest extends TestCase
{
    public function testPagesThroughTheWholeQueue(): void
    {
        $rows = array_map(static fn(int $id): array => ['entity_id' => $id], range(1, 51));
        $resource = $this->createMock(ApprovalRequest::class);
        $resource->expects(self::once())->method('getForCompany')->with(5, 51, 50)->willReturn($rows);

        $block = $this->block($resource, 2);

        self::assertCount(50, $block->getRequests());
        self::assertSame('url:p=1', $block->getPreviousPageUrl());
        self::assertSame('url:p=3', $block->getNextPageUrl());
    }

    public function testLastPageHasNoNextLink(): void
    {
        $resource = $this->createMock(ApprovalRequest::class);
        $resource->method('getForCompany')->with(5, 51, 0)->willReturn([['entity_id' => 1]]);

        $block = $this->block($resource, 1);

        self::assertCount(1, $block->getRequests());
        self::assertNull($block->getPreviousPageUrl());
        self::assertNull($block->getNextPageUrl());
    }

    public function testFormatsAmountsQuantitiesAndOrderNumbers(): void
    {
        $block = $this->block($this->createMock(ApprovalRequest::class), 1);

        self::assertSame('$118.00', $block->formatBasePrice('118.0000'));
        self::assertSame('6', $block->formatQty('6.0000'));
        self::assertSame('1.5', $block->formatQty('1.5000'));
        self::assertSame('000000045', $block->getOrderNumber(['order_id' => '59', 'order_increment_id' => '000000045']));
        self::assertSame('59', $block->getOrderNumber(['order_id' => '59', 'order_increment_id' => null]));
    }

    private function block(ApprovalRequest $resource, int $page): Requests
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('p', 1)->willReturn((string)$page);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route, array $params): string => 'url:p=' . $params['_query']['p']
        );
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        $currency = $this->createMock(Currency::class);
        $currency->method('format')->willReturnCallback(
            static fn(float $amount, array $options, bool $includeContainer): string => $includeContainer ? 'container' : '$' . number_format($amount, 2)
        );
        $store = $this->createMock(Store::class);
        $store->method('getBaseCurrency')->willReturn($currency);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $context->method('getStoreManager')->willReturn($storeManager);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(5);
        $companyContext = $this->createMock(CompanyContext::class);
        $companyContext->method('getCompany')->willReturn($company);

        return new Requests($context, $companyContext, $resource, $this->createMock(Json::class));
    }
}
