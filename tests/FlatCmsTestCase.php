<?php

declare(strict_types=1);

namespace FlatCms\Tests;

use FlatCms\App;
use FlatCms\Support\Fs;
use PHPUnit\Framework\TestCase;

/**
 * Base class that gives each test an isolated project root with a small content tree.
 */
abstract class FlatCmsTestCase extends TestCase
{
    protected string $root;

    protected FakeSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new FakeSession();
        $this->root = sys_get_temp_dir() . '/flatcms-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
        $this->seed();
    }

    protected function tearDown(): void
    {
        (new Fs($this->root))->deleteDir($this->root);
        parent::tearDown();
    }

    protected function app(): App
    {
        $this->session ??= new FakeSession();

        $app = new App($this->root, $this->session);
        $app->boot();
        $app->build();

        return $app;
    }

    protected function write(string $path, string $contents): void
    {
        (new Fs($this->root))->write($path, $contents);
    }

    /**
     * Copy a folder from the real project into the fixture root. Used by tests
     * that exercise modules and themes.
     */
    protected function copyFromProject(string $relative): void
    {
        $source = dirname(__DIR__) . '/' . $relative;
        if (!is_dir($source)) {
            return;
        }

        (new Fs($this->root))->copyDir($source, $relative);
    }

    private function seed(): void
    {
        $this->write('config/site.yaml', "site:\n  title: Test\n  theme: default\ncache:\n  check: true\n");
        $this->write('config/redirects.yaml', "{}\n");
        $this->write('config/schemas/product.yaml', "type: product\nfields:\n  price: { type: float, required: true }\n  tags: { type: list }\nindexed: [price, tags]\n");

        $this->write('content/index/item.yaml', "type: page\ntitle: Home\norder: 0\n");
        $this->write('content/index/content.md', "# Hello\n\nWelcome.");
        $this->write('content/about/item.yaml', "type: page\ntitle: About\norder: 10\n");
        $this->write('content/products/item.yaml', "type: section\ntitle: Products\norder: 20\n");
        $this->write('content/products/teapot/item.yaml', "type: product\ntitle: Teapot\nprice: 1200\ntags: [tea]\nsku: T-1\norder: 1\n");
        $this->write('content/products/teapot/content.md', "A teapot.");
        $this->write('content/products/cup/item.yaml', "type: product\ntitle: Cup\nprice: 300\ntags: [tea, kitchen]\norder: 2\n");
        $this->write('content/hidden/item.yaml', "type: page\ntitle: Hidden\npublished: false\n");
    }
}
