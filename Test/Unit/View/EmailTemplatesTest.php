<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\View;

use Magento\Framework\Filter\Template;
use PHPUnit\Framework\TestCase;

class EmailTemplatesTest extends TestCase
{
    /** @dataProvider templates */
    public function testEmailShowsTheRequestedCartAndAButton(string $file, string $urlVar): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../view/frontend/email/' . $file);

        self::assertStringContainsString('design/email/header_template', $html);
        self::assertSame(1, preg_match(Template::LOOP_PATTERN, $html, $match));
        self::assertSame('items', trim($match['loopData']));
        foreach (['item.name', 'item.sku', 'item.qty', 'item.total'] as $field) {
            self::assertStringContainsString('{{var ' . $field . '}}', $match['loopBody']);
        }
        // The for directive resolves only variables; a {{trans}} in the loop body renders empty.
        self::assertStringNotContainsString('{{trans', $match['loopBody']);
        self::assertStringContainsString('{{var subtotal}}', $html);
        self::assertStringContainsString('<a href="{{var ' . $urlVar . '}}"', $html);
    }

    public static function templates(): array
    {
        return [
            'request' => ['approval_request.html', 'approval_url'],
            'approved' => ['approval_approved.html', 'checkout_url'],
            'rejected' => ['approval_rejected.html', 'checkout_url'],
        ];
    }
}
