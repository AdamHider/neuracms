<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Payment;

use FlatCms\Modules\Shop\Order\Order;

/**
 * Contract for a payment provider. Providers are registered by the shop module
 * and selected per order, so adding a real gateway means adding one class.
 */
interface PaymentProvider
{
    public function name(): string;

    public function title(): string;

    public function enabled(): bool;

    /**
     * Start the payment. Return a URL to redirect to, or null when the provider
     * settles the order immediately (for example cash on delivery).
     */
    public function start(Order $order): ?string;

    /**
     * Whether the provider confirms payment without an external callback.
     */
    public function autoConfirm(): bool;
}
