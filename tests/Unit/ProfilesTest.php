<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Core\Request;
use FlatCms\Support\Fs;
use FlatCms\Tests\FlatCmsTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The three ready-made site profiles must boot and serve their pages with only
 * the modules they enable.
 */
final class ProfilesTest extends FlatCmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['builder', 'shop', 'contact-form'] as $module) {
            $this->copyFromProject('modules/' . $module);
        }
        $this->copyFromProject('src/Admin');
        $this->copyFromProject('themes/default');
        $this->copyFromProject('profiles');
        $this->copyFromProject('config/schemas');
    }

    private function install(string $profile): \FlatCms\App
    {
        $fs = new Fs($this->root);
        $fs->deleteDir('content');
        $fs->copyDir('profiles/' . $profile . '/content', 'content');
        $fs->copy('profiles/' . $profile . '/site.yaml', 'config/site.yaml');
        if (is_dir($this->project('profiles/' . $profile . '/regions'))) {
            $fs->copyDir('profiles/' . $profile . '/regions', 'config/regions');
        }

        return $this->app();
    }

    private function get(\FlatCms\App $app, string $path): \FlatCms\Core\Response
    {
        return $app->handle(new Request('GET', $path, [], [], [], [], []));
    }

    /**
     * @return array<string, array{profile: string, paths: list<string>}>
     */
    public static function profiles(): array
    {
        return [
            'visiting-card' => [
                'profile' => 'visiting-card',
                'paths' => ['/', '/about', '/services', '/contact'],
            ],
            'corporate' => [
                'profile' => 'corporate',
                'paths' => ['/', '/about', '/services', '/services/design', '/team', '/contacts'],
            ],
            'shop' => [
                'profile' => 'shop',
                'paths' => ['/', '/catalog', '/catalog/teapot', '/about', '/delivery'],
            ],
        ];
    }

    /** @param list<string> $paths */
    #[DataProvider('profiles')]
    public function testProfileServesAllPages(string $profile, array $paths): void
    {
        $app = $this->install($profile);

        foreach ($paths as $path) {
            $response = $this->get($app, $path);
            self::assertSame(200, $response->status(), "{$profile}: {$path}");
            self::assertNotSame('', trim($response->body()), "{$profile}: {$path} is empty");
        }
    }

    public function testVisitingCardHomeRendersBuilderBlocks(): void
    {
        $app = $this->install('visiting-card');

        $body = $this->get($app, '/')->body();

        self::assertStringContainsString('Студия «Верста»', $body);
        self::assertStringContainsString('block--columns', $body);
        self::assertStringContainsString('block--media', $body);
        self::assertStringContainsString('/media/index/logo.svg', $body);
        self::assertStringContainsString('/contact', $body);
    }

    public function testVisitingCardServicesRenderAsElementCards(): void
    {
        $app = $this->install('visiting-card');

        $body = $this->get($app, '/services')->body();

        self::assertStringContainsString('element-card', $body);
        self::assertStringContainsString('Сайт-визитка', $body);
        self::assertStringContainsString('30000', $body);
    }

    public function testVisitingCardRendersEditableHeaderAndFooterRegions(): void
    {
        $app = $this->install('visiting-card');

        $body = $this->get($app, '/')->body();

        // Header region: the logo comes from the region's own media folder and the
        // navigation is built from the menu-item elements.
        self::assertStringContainsString('/media/_regions/header/logo.svg', $body);
        self::assertStringContainsString('site-nav', $body);
        self::assertStringContainsString('>Услуги<', $body);

        // Footer region: rendered on every page.
        self::assertStringContainsString('Сайты для малого бизнеса с 2016 года.', $body);

        // The theme's hard-coded fallback navigation must not appear when a region
        // is defined.
        self::assertStringNotContainsString('site-header__inner', $body);
    }

    public function testCorporateProfileDisablesTheShop(): void
    {
        $app = $this->install('corporate');

        self::assertNull($app->module('shop'));
        self::assertSame(404, $this->get($app, '/cart')->status());
        self::assertStringNotContainsString('В корзину', $this->get($app, '/')->body());
    }

    public function testShopProfileListsProductsAndAcceptsAnOrder(): void
    {
        $app = $this->install('shop');
        self::assertNotNull($app->module('shop'));

        $catalog = $this->get($app, '/catalog')->body();
        self::assertStringContainsString('Чайник заварочный', $catalog);
        self::assertStringContainsString('2 400', $catalog);

        $app->handle(new Request('POST', '/cart/add', [], ['sku' => 'COFFEE-1', 'qty' => '2'], [], [], []));
        $order = $app->handle(new Request('POST', '/checkout', [], [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'phone' => '+79000000000',
            'address' => 'Невский 1',
            'provider' => 'manual',
        ], [], [], []));

        self::assertSame(303, $order->status());
        self::assertStringStartsWith('/order/', (string) $order->headers()['Location']);

        $orderId = basename((string) $order->headers()['Location']);
        $page = $this->get($app, '/order/' . $orderId);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('3 600', $page->body());
    }
}
