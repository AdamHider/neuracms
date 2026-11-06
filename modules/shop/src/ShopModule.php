<?php

declare(strict_types=1);

namespace FlatCms\Modules\Shop;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Contracts\Module;
use FlatCms\Core\Request;
use FlatCms\Core\Response;
use FlatCms\Modules\Shop\Cart\Cart;
use FlatCms\Modules\Shop\Cart\CartLine;
use FlatCms\Modules\Shop\Order\Order;
use FlatCms\Modules\Shop\Order\OrderRepository;
use FlatCms\Modules\Shop\Payment\ManualProvider;
use FlatCms\Modules\Shop\Payment\PaymentProvider;
use FlatCms\Modules\Shop\Payment\PaymentRegistry;

/**
 * Shop module: catalog listing, product page helper, cart, checkout and orders.
 * Payment gateways are pluggable through PaymentProvider.
 */
final class ShopModule implements Module
{
    private ?App $app = null;

    private ?Cart $cart = null;

    private ?OrderRepository $orders = null;

    private ?PaymentRegistry $payments = null;

    public function boot(App $app): void
    {
        $this->app = $app;
        $app->config->mergeFile('modules/shop/config/config.yaml');

        $this->cart = new Cart($app->session());
        $this->orders = new OrderRepository($app->fs);
        $this->payments = new PaymentRegistry();
        $this->registerProviders($app);

        $router = $app->router;
        $router->add('GET', '/cart', fn (Request $r, array $p): Response => $this->showCart($r));
        $router->add('POST', '/cart/add', fn (Request $r, array $p): Response => $this->addToCart($r));
        $router->add('POST', '/cart/update', fn (Request $r, array $p): Response => $this->updateCart($r));
        $router->add('POST', '/cart/remove', fn (Request $r, array $p): Response => $this->removeFromCart($r));
        $router->add('GET', '/checkout', fn (Request $r, array $p): Response => $this->checkoutForm($r));
        $router->add('POST', '/checkout', fn (Request $r, array $p): Response => $this->checkoutSubmit($r));
        $router->add('GET', '/order/{id}', fn (Request $r, array $p): Response => $this->orderPage($p));

        $app->events->on('site.data', function (array $site): array {
            $site['cart_count'] = $this->cart?->count() ?? 0;
            $site['nav'][] = ['title' => 'Корзина', 'url' => '/cart'];

            return $site;
        });

        $app->events->on('render.before', function (string $name, array $data): void {
            // reserved for future catalog widgets
        });
    }

    public function build(): void
    {
    }

    public function cart(): ?Cart
    {
        return $this->cart;
    }

    public function payments(): ?PaymentRegistry
    {
        return $this->payments;
    }

    public function orders(): ?OrderRepository
    {
        return $this->orders;
    }

    private function registerProviders(App $app): void
    {
        $registry = $this->payments;
        if ($registry === null) {
            return;
        }

        $registry->register(new ManualProvider(
            (bool) $app->config->get('providers.manual.enabled', true),
            (string) $app->config->get('providers.manual.title', 'Оплата при получении'),
        ));

        // Other modules add providers through this filter.
        $app->events->emit('shop.providers', $registry);
    }

    private function addToCart(Request $request): Response
    {
        $sku = trim((string) $request->input('sku', ''));
        $qty = max(1, (int) $request->input('qty', 1));
        $item = $this->findBySku($sku);

        if ($item === null) {
            return Response::redirect('/cart', 303);
        }

        $this->cart?->add(new CartLine(
            $sku,
            $item->id(),
            $item->title(),
            (float) $item->get('price', 0),
            $qty,
        ));

        if ($request->isAjax()) {
            return Response::json(['count' => $this->cart?->count() ?? 0, 'total' => $this->cart?->total() ?? 0]);
        }

        return Response::redirect('/cart', 303);
    }

    private function updateCart(Request $request): Response
    {
        $qtys = $request->input('qty', []);
        if (is_array($qtys)) {
            foreach ($qtys as $sku => $qty) {
                $this->cart?->setQty((string) $sku, (int) $qty);
            }
        }

        return Response::redirect('/cart', 303);
    }

    private function removeFromCart(Request $request): Response
    {
        $this->cart?->remove((string) $request->input('sku', ''));

        return Response::redirect('/cart', 303);
    }

    private function showCart(Request $request): Response
    {
        $app = $this->requireApp();

        return Response::html($app->renderer->renderTemplate('cart', [
            'cart' => $this->cart,
            'lines' => $this->cart?->lines() ?? [],
            'total' => $this->cart?->total() ?? 0.0,
            'currency' => (string) $app->config->get('shop.currency_symbol', '₽'),
        ]));
    }

    private function checkoutForm(Request $request, array $errors = [], array $values = []): Response
    {
        $app = $this->requireApp();

        if ($this->cart === null || $this->cart->isEmpty()) {
            return Response::redirect('/cart', 303);
        }

        return Response::html($app->renderer->renderTemplate('checkout', [
            'lines' => $this->cart->lines(),
            'total' => $this->cart->total(),
            'currency' => (string) $app->config->get('shop.currency_symbol', '₽'),
            'providers' => $this->payments?->enabled() ?? [],
            'errors' => $errors,
            'values' => $values,
        ]));
    }

    private function checkoutSubmit(Request $request): Response
    {
        $app = $this->requireApp();
        if ($this->cart === null || $this->cart->isEmpty()) {
            return Response::redirect('/cart', 303);
        }

        $values = [
            'name' => trim((string) $request->input('name', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'address' => trim((string) $request->input('address', '')),
            'comment' => trim((string) $request->input('comment', '')),
            'provider' => trim((string) $request->input('provider', '')),
        ];

        $errors = $this->validate($values);
        if ($errors !== []) {
            return $this->checkoutForm($request, $errors, $values)->setStatus(422);
        }

        $provider = $this->payments?->get($values['provider']) ?? $this->payments?->default();
        if ($provider === null || !$provider->enabled()) {
            $errors['provider'] = 'Выберите способ оплаты.';

            return $this->checkoutForm($request, $errors, $values)->setStatus(422);
        }

        $lines = [];
        foreach ($this->cart->lines() as $line) {
            $lines[] = $line->toArray();
        }

        $order = new Order(
            $this->newOrderId(),
            Order::STATUS_NEW,
            $this->cart->total(),
            (string) $app->config->get('shop.currency', 'RUB'),
            $lines,
            [
                'name' => $values['name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'address' => $values['address'],
                'comment' => $values['comment'],
            ],
            $provider->name(),
            date('c'),
        );

        $this->orders?->create($order);
        $app->events->emit('shop.order.created', $order);

        $redirect = $provider->start($order);
        if ($provider->autoConfirm()) {
            $this->orders?->updateStatus($order->id, Order::STATUS_PAID);
        }

        $this->cart->clear();

        return Response::redirect($redirect ?? '/order/' . $order->id, 303);
    }

    private function orderPage(array $params): Response
    {
        $app = $this->requireApp();
        $order = $this->orders?->find((string) $params['id']);

        if ($order === null) {
            return Response::html($app->renderer->renderTemplate('404', ['site' => []]), 404);
        }

        return Response::html($app->renderer->renderTemplate('order', [
            'order' => $order,
            'currency' => (string) $app->config->get('shop.currency_symbol', '₽'),
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if ($values['name'] === '') {
            $errors['name'] = 'Укажите имя.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        }
        if (mb_strlen($values['phone']) < 5) {
            $errors['phone'] = 'Укажите телефон.';
        }
        if (mb_strlen($values['address']) < 5) {
            $errors['address'] = 'Укажите адрес доставки.';
        }

        return $errors;
    }

    private function findBySku(string $sku): ?Item
    {
        if ($sku === '') {
            return null;
        }

        $app = $this->requireApp();
        $item = $app->query()->type('product')->where('sku', '=', $sku)->first();
        if ($item !== null) {
            return $item;
        }

        return $app->storage->load($sku);
    }

    private function newOrderId(): string
    {
        $prefix = (string) $this->requireApp()->config->get('shop.order_prefix', 'ORD');

        return $prefix . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function requireApp(): App
    {
        if ($this->app === null) {
            throw new \RuntimeException('Shop module is not booted');
        }

        return $this->app;
    }
}
