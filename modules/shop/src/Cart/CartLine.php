<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Cart;

/**
 * A single cart line. Prices are captured when the line is added so later price
 * changes do not silently alter an open order.
 */
final class CartLine
{
    public function __construct(
        public readonly string $sku,
        public readonly string $itemId,
        public readonly string $title,
        public readonly float $price,
        public int $qty,
    ) {
    }

    public function total(): float
    {
        return $this->price * $this->qty;
    }

    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'item_id' => $this->itemId,
            'title' => $this->title,
            'price' => $this->price,
            'qty' => $this->qty,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['sku'] ?? ''),
            (string) ($data['item_id'] ?? ''),
            (string) ($data['title'] ?? ''),
            (float) ($data['price'] ?? 0),
            (int) ($data['qty'] ?? 0),
        );
    }
}
