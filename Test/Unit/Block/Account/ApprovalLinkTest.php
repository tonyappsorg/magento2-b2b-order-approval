<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Block\Account;

use Magento\Customer\Block\Account\SortLinkInterface;
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

    public function testLinkIsSortedIntoTheCompanySection(): void
    {
        // The account navigation sorts only SortLinkInterface children and puts every other link last.
        self::assertTrue(is_subclass_of(ApprovalLink::class, SortLinkInterface::class));

        $layout = simplexml_load_file(__DIR__ . '/../../../../view/frontend/layout/customer_account.xml');
        $sortOrder = $layout->xpath('//argument[@name="sortOrder"]');
        // Between the B2b company section delimiters (245 and 234).
        self::assertSame('238', (string) $sortOrder[0]);
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
