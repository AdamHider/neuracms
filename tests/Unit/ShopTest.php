<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Core\Request;
use FlatCms\Modules\Shop\Cart\Cart;
use FlatCms\Modules\Shop\Cart\CartLine;
use FlatCms\Modules\Shop\Order\Order;
use FlatCms\Modules\Shop\Order\OrderRepository;
use FlatCms\Modules\Shop\Payment\ManualProvider;
use FlatCms\Modules\Shop\Payment\PaymentRegistry;
use FlatCms\Support\Fs;
use FlatCms\Tests\FakeSession;
use FlatCms\Tests\FlatCmsTestCase;

final class ShopTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyFromProject('modules/shop');
        $this->copyFromProject('themes/default');
    }

    private function appWithShop(): \FlatCms\App
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\nmodules:\n  shop: { enabled: true }\n");

        return $this->app();
    }

    public function testCartAddsAndTotalsLines(): void
    {
        $session = new FakeSession();
        $cart = new Cart($session);
        $cart->add(new CartLine('T-1', 'products/teapot', 'Teapot', 1200.0, 2));
        $cart->add(new CartLine('T-1', 'products/teapot', 'Teapot', 1200.0, 1));
        $cart->add(new CartLine('C-1', 'products/cup', 'Cup', 300.0, 1));

        self::assertSame(4, $cart->count());
        self::assertSame(3900.0, $cart->total());
        self::assertCount(2, $cart->lines());
    }

    public function testCartSetQtyZeroRemovesLine(): void
    {
        $cart = new Cart(new FakeSession());
        $cart->add(new CartLine('T-1', 'products/teapot', 'Teapot', 1200.0, 1));
        $cart->setQty('T-1', 0);

        self::assertTrue($cart->isEmpty());
    }

    public function testOrderRepositoryPersistsAndFindsOrder(): void
    {
        $repo = new OrderRepository(new Fs($this->root));
        $order = new Order('ORD-1', Order::STATUS_NEW, 1500.0, 'RUB', [
            ['sku' => 'T-1', 'item_id' => 'products/teapot', 'title' => 'Teapot', 'price' => 1500.0, 'qty' => 1],
        ], ['name' => 'Ivan', 'email' => 'ivan@example.com'], 'manual', date('c'));

        $repo->create($order);
        $found = $repo->find('ORD-1');

        self::assertNotNull($found);
        self::assertSame('ORD-1', $found->id);
        self::assertSame(1500.0, $found->total);
        self::assertSame(Order::STATUS_NEW, $found->status);
    }

    public function testOrderStatusUpdateSetsPaidAt(): void
    {
        $repo = new OrderRepository(new Fs($this->root));
        $repo->create(new Order('ORD-2', Order::STATUS_NEW, 100.0, 'RUB', [], [], 'manual', date('c')));

        $paid = $repo->updateStatus('ORD-2', Order::STATUS_PAID);

        self::assertNotNull($paid);
        self::assertTrue($paid->isPaid());
        self::assertNotNull($paid->paidAt);
    }

    public function testPaymentRegistryReturnsEnabledProviders(): void
    {
        $registry = new PaymentRegistry();
        $registry->register(new ManualProvider(true, 'Manual', 'manual'));
        $registry->register(new ManualProvider(false, 'Disabled', 'disabled'));

        self::assertCount(1, $registry->enabled());
        self::assertSame('Manual', $registry->default()?->title());
    }

    public function testCartAddThroughHttp(): void
    {
        $app = $this->appWithShop();
        self::assertNotNull($app->module('shop'));

        $response = $app->handle($this->post('/cart/add', ['sku' => 'T-1', 'qty' => '2']));

        self::assertSame(303, $response->status());
        self::assertSame('/cart', $response->headers()['Location']);
    }

    public function testCatalogPageRendersProducts(): void
    {
        $app = $this->appWithShop();

        $response = $app->handle(new Request('GET', '/products', [], [], [], [], []));
        $body = $response->body();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Teapot', $body);
        self::assertStringContainsString('Корзина', $body);
    }

    private function post(string $path, array $data): Request
    {
        return new Request('POST', $path, [], $data, [], [], []);
    }
}
