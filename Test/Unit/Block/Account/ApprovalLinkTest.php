<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Block\Account;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bOrderApproval\Block\Account\ApprovalLink;

class ApprovalLinkTest extends TestCase
{
    public function testHiddenFromCustomersOutsideACompany(): void
    {
        $companyContext = $this->createMock(CompanyContext::class);
        $companyContext->method('isAllowed')->willReturn(false);

        self::assertSame('', $this->link($companyContext)->toHtml());
    }

    public function testHiddenFromCompanyMembersWhoCannotApprove(): void
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getAdminCustomerId')->willReturn(3);
        $companyContext = $this->createMock(CompanyContext::class);
        $companyContext->method('isAllowed')->willReturn(true);
        $companyContext->method('getCompany')->willReturn($company);
        $companyContext->method('getCustomerId')->willReturn(21);

        self::assertSame('', $this->link($companyContext)->toHtml());
    }

    private function link(CompanyContext $companyContext): ApprovalLink
    {
        return new ApprovalLink(
            $this->createMock(Context::class),
            $this->createMock(DefaultPathInterface::class),
            $companyContext
        );
    }
}
