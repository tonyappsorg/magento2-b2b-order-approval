<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;

class CartFingerprint
{
    public function __construct(private readonly Json $json)
    {
    }

    public function hash(Quote $quote): string
    {
        return hash('sha256', $this->json->serialize($this->snapshot($quote)));
    }

    /** @return array<string, mixed> */
    public function snapshot(Quote $quote): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $options = [];
            foreach ($item->getOptions() as $option) {
                $options[] = ['code' => (string)$option->getCode(), 'value' => (string)$option->getValue()];
            }
            usort($options, static fn(array $a, array $b): int => [$a['code'], $a['value']] <=> [$b['code'], $b['value']]);
            $items[] = [
                'product_id' => (int)$item->getProductId(),
                'sku' => (string)$item->getSku(),
                'name' => (string)$item->getName(),
                'qty' => number_format((float)$item->getQty(), 4, '.', ''),
                'price' => number_format((float)$item->getBasePrice(), 4, '.', ''),
                'row_total' => number_format((float)$item->getBaseRowTotal(), 4, '.', ''),
                'options' => $options,
            ];
        }
        usort($items, static fn(array $a, array $b): int => [$a['product_id'], $a['sku']] <=> [$b['product_id'], $b['sku']]);

        return [
            'items' => $items,
            'coupon' => (string)$quote->getCouponCode(),
            'base_subtotal' => number_format((float)$quote->getBaseSubtotal(), 4, '.', ''),
        ];
    }
}
