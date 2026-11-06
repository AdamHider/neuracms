<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Order;

/**
 * An order as stored on disk. Status transitions are handled by OrderRepository.
 */
final class Order
{
    public const STATUS_NEW = 'new';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param list<array<string, mixed>> $lines
     * @param array<string, mixed>       $customer
     */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly float $total,
        public readonly string $currency,
        public readonly array $lines,
        public readonly array $customer,
        public readonly string $provider,
        public readonly string $createdAt,
        public readonly ?string $paidAt = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'currency' => $this->currency,
            'lines' => $this->lines,
            'customer' => $this->customer,
            'provider' => $this->provider,
            'created_at' => $this->createdAt,
            'paid_at' => $this->paidAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['status'] ?? self::STATUS_NEW),
            (float) ($data['total'] ?? 0),
            (string) ($data['currency'] ?? 'RUB'),
            is_array($data['lines'] ?? null) ? $data['lines'] : [],
            is_array($data['customer'] ?? null) ? $data['customer'] : [],
            (string) ($data['provider'] ?? 'manual'),
            (string) ($data['created_at'] ?? ''),
            isset($data['paid_at']) && $data['paid_at'] !== null ? (string) $data['paid_at'] : null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
