<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class AccountPageLayoutTest extends TestCase
{
    /** Company data must never be served from the full page cache, and the page needs the account menu. */
    public function testApprovalQueueIsUncachedAndUsesTheAccountLayout(): void
    {
        $layout = simplexml_load_file(__DIR__ . '/../../../view/frontend/layout/b2borderapproval_approval_index.xml');

        self::assertSame('customer_account', (string)$layout->update['handle']);
        self::assertSame('false', (string)$layout->xpath('//block[@name="tonyapps.b2b.order.approval.requests"]')[0]['cacheable']);
    }
}
