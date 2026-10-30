<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\Config as B2bConfig;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2bOrderApproval\Model\OrderApprovalPolicy;

class OrderApprovalPolicyTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private MembershipManagementInterface&MockObject $membership;
    private CompanyRepositoryInterface&MockObject $companies;
    private B2bConfig&MockObject $b2bConfig;
    private CompanyResource&MockObject $companyResource;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->membership = $this->createMock(MembershipManagementInterface::class);
        $this->companies = $this->createMock(CompanyRepositoryInterface::class);
        $this->b2bConfig = $this->createMock(B2bConfig::class);
        $this->companyResource = $this->createMock(CompanyResource::class);
    }

    public function testActiveCompanyMemberRequiresApprovalAtTheConfiguredThreshold(): void
    {
        $policy = $this->policy(true, 100.0, 1);
        $quote = $this->quote(101, 100.0);

        self::assertSame(101, $quote->getCustomerId());
        self::assertSame(1, $quote->getStoreId());
        self::assertSame(100.0, $quote->getBaseSubtotal());
        self::assertTrue($policy->requiresApproval($quote));
    }

    public function testGuestCheckoutMethodOnACustomerCartStillRequiresApproval(): void
    {
        $policy = $this->policy(true, 100.0, 1);
        $quote = $this->quote(101, 100.0);
        $quote->setOrigData('customer_id', 101);
        $quote->setCustomerId(null);

        self::assertTrue($policy->requiresApproval($quote));
    }

    public function testCompanyAdministratorCanPlaceOrdersDirectly(): void
    {
        $policy = $this->policy(true, 0.0, 7);

        self::assertFalse($policy->requiresApproval($this->quote(7, 500.0)));
    }

    public function testBelowThresholdDoesNotRequireApproval(): void
    {
        $policy = $this->policy(true, 100.01, 1);

        self::assertFalse($policy->requiresApproval($this->quote(101, 100.0)));
    }

    public function testDisabledB2bDoesNotRequireApproval(): void
    {
        $this->b2bConfig->method('isEnabled')->willReturn(false);
        $policy = $this->makePolicy();
        self::assertFalse($policy->requiresApproval($this->quote(101, 500.0)));
    }

    public function testInactiveCompanyDoesNotRequireApproval(): void
    {
        $policy = $this->policy(true, 0.0, 1, false);
        self::assertFalse($policy->requiresApproval($this->quote(101, 500.0)));
    }

    private function policy(bool $enabled, float $minimum, int $adminCustomerId, bool $active = true): OrderApprovalPolicy
    {
        $this->scopeConfig->method('isSetFlag')->willReturn($enabled);
        $this->scopeConfig->method('getValue')->willReturn($minimum);
        $this->b2bConfig->method('isEnabled')->willReturn(true);
        $this->membership->method('getCompanyId')->willReturn(5);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getAdminCustomerId')->willReturn($adminCustomerId);
        $this->companies->method('getById')->willReturn($company);
        $this->companyResource->method('isActivePath')->willReturn($active);
        return $this->makePolicy();
    }

    private function makePolicy(): OrderApprovalPolicy
    {
        return new OrderApprovalPolicy(
            $this->scopeConfig,
            $this->membership,
            $this->companies,
            $this->b2bConfig,
            $this->companyResource
        );
    }

    private function quote(int $customerId, float $subtotal): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId'])
            ->getMock();
        $quote->setData(['customer_id' => $customerId, 'store_id' => 1, 'base_subtotal' => $subtotal]);
        $quote->method('getStoreId')->willReturn(1);
        return $quote;
    }
}
