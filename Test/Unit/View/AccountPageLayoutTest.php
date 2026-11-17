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

    public function testApprovalTemplatesUseHyvaButtonsAndTailwindSource(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertStringContainsString(
            'btn-primary',
            file_get_contents($root . '/view/frontend/templates/cart/request-approval.phtml')
        );
        self::assertStringContainsString(
            'btn-primary',
            file_get_contents($root . '/view/frontend/templates/approval/requests.phtml')
        );
        self::assertStringContainsString(
            'form-input',
            file_get_contents($root . '/view/frontend/templates/approval/requests.phtml')
        );
        self::assertStringContainsString(
            '@source "../templates/**/*.phtml"',
            file_get_contents($root . '/view/frontend/tailwind/module.css')
        );
        $styles = file_get_contents($root . '/view/frontend/tailwind/module.css');
        self::assertStringContainsString('px-2 py-2 text-left text-sm font-semibold', $styles);
        self::assertStringContainsString('tbody tr:nth-child(even)', $styles);
        self::assertStringContainsString('b2b-table-wrapper card mb-4', file_get_contents($root . '/view/frontend/templates/approval/requests.phtml'));
    }
}
