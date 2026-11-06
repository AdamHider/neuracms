<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Payment;

use FlatCms\Modules\Shop\Order\Order;

/**
 * Offline provider: the order is accepted and paid on delivery. Useful default
 * when no payment gateway is configured.
 */
final class ManualProvider implements PaymentProvider
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $title = 'Оплата при получении',
        private readonly string $name = 'manual',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function start(Order $order): ?string
    {
        return null;
    }

    public function autoConfirm(): bool
    {
        return false;
    }
}
