<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Cart;

use FlatCms\Core\SessionStore;

/**
 * Cart stored in the session. Quantity 0 removes a line.
 */
final class Cart
{
    private const KEY = 'shop.cart';

    public function __construct(private readonly SessionStore $session)
    {
    }

    /**
     * @return array<string, CartLine>
     */
    public function lines(): array
    {
        $raw = $this->session->get(self::KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $sku => $data) {
            if (is_array($data)) {
                $lines[(string) $sku] = CartLine::fromArray($data);
            }
        }

        return $lines;
    }

    public function add(CartLine $line): void
    {
        $lines = $this->lines();
        if (isset($lines[$line->sku])) {
            $lines[$line->sku]->qty += $line->qty;
        } else {
            $lines[$line->sku] = $line;
        }

        $this->save($lines);
    }

    public function setQty(string $sku, int $qty): void
    {
        $lines = $this->lines();
        if ($qty <= 0) {
            unset($lines[$sku]);
        } elseif (isset($lines[$sku])) {
            $lines[$sku]->qty = $qty;
        }

        $this->save($lines);
    }

    public function remove(string $sku): void
    {
        $this->setQty($sku, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->lines() as $line) {
            $total += $line->qty;
        }

        return $total;
    }

    public function total(): float
    {
        $total = 0.0;
        foreach ($this->lines() as $line) {
            $total += $line->total();
        }

        return round($total, 2);
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /**
     * @param array<string, CartLine> $lines
     */
    private function save(array $lines): void
    {
        $data = [];
        foreach ($lines as $sku => $line) {
            $data[$sku] = $line->toArray();
        }

        $this->session->set(self::KEY, $data);
    }
}
