<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Unit\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bOrderApproval\Model\CartFingerprint;

class CartFingerprintTest extends TestCase
{
    public function testFingerprintChangesWhenCartContentOrSubtotalChanges(): void
    {
        $quote = $this->quote();
        $fingerprint = new CartFingerprint(new Json());
        $original = $fingerprint->hash($quote);

        $quote->setData('base_subtotal', 42.0);
        self::assertNotSame($original, $fingerprint->hash($quote));

        $quote->setData('base_subtotal', 40.0);
        $item = $quote->getAllVisibleItems()[0];
        $item->setData('qty', 2);
        self::assertNotSame($original, $fingerprint->hash($quote));
    }

    public function testSnapshotContainsVisibleLinesAndCoupon(): void
    {
        $quote = $this->quote();
        $snapshot = (new CartFingerprint(new Json()))->snapshot($quote);

        self::assertSame('SAVE', $snapshot['coupon']);
        self::assertCount(1, $snapshot['items']);
        self::assertSame('SKU-1', $snapshot['items'][0]['sku']);
        self::assertSame('1.0000', $snapshot['items'][0]['qty']);
        self::assertSame('40.0000', $snapshot['base_subtotal']);
    }

    private function quote(): Quote
    {
        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOptions'])
            ->getMock();
        $item->setData([
            'product_id' => 10,
            'sku' => 'SKU-1',
            'name' => 'Product One',
            'qty' => 1,
            'base_price' => 40.0,
            'base_row_total' => 40.0,
        ]);
        $item->method('getOptions')->willReturn([]);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems'])
            ->getMock();
        $quote->setData(['coupon_code' => 'SAVE', 'base_subtotal' => 40.0]);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        return $quote;
    }
}
