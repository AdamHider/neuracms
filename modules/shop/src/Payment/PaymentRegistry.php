<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop\Payment;

/**
 * Registry of payment providers. The shop module registers the built-in ones and
 * other modules may add their own through the shop.providers filter.
 */
final class PaymentRegistry
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * @return array<string, PaymentProvider>
     */
    public function enabled(): array
    {
        return array_filter($this->providers, static fn (PaymentProvider $p): bool => $p->enabled());
    }

    public function get(string $name): ?PaymentProvider
    {
        return $this->providers[$name] ?? null;
    }

    public function default(): ?PaymentProvider
    {
        $enabled = $this->enabled();

        return $enabled === [] ? null : reset($enabled);
    }
}
