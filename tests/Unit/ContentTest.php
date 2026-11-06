<?php

declare(strict_types=1);

namespace FlatCms\Tests\Unit;

use FlatCms\Content\Item;
use FlatCms\Content\Markdown;
use FlatCms\Content\Storage;
use FlatCms\Core\Events;
use FlatCms\Support\Fs;
use FlatCms\Tests\FlatCmsTestCase;

final class ContentTest extends FlatCmsTestCase
{
    private function storage(): Storage
    {
        return new Storage(new Fs($this->root), new Events());
    }

    public function testLoadsItemProperties(): void
    {
        $item = $this->storage()->load('products/teapot');

        self::assertInstanceOf(Item::class, $item);
        self::assertSame('products/teapot', $item->id());
        self::assertSame('product', $item->type());
        self::assertSame('Teapot', $item->title());
        self::assertSame(1200, $item->get('price'));
        self::assertSame('/products/teapot', $item->url());
        self::assertSame('products', $item->parentId());
    }

    public function testMarkdownRenders(): void
    {
        $html = (new Markdown())->parse("# Hello\n\nWorld");

        self::assertStringContainsString('<h1>Hello</h1>', $html);
        self::assertStringContainsString('<p>World</p>', $html);
    }

    public function testItemHtmlIsRenderedLazily(): void
    {
        $item = $this->storage()->load('index');

        self::assertStringContainsString('<h1>Hello</h1>', $item->html());
        self::assertSame("# Hello\n\nWelcome.", $item->body());
    }

    public function testScanSkipsUnderscoreFolders(): void
    {
        $this->write('content/_drafts/item.yaml', "title: Draft\n");

        $ids = iterator_to_array($this->storage()->scan());

        self::assertNotContains('_drafts', $ids);
        self::assertContains('products/teapot', $ids);
    }

    public function testRejectsPathTraversal(): void
    {
        self::assertNull($this->storage()->load('../../config'));
        self::assertNull($this->storage()->load('../vendor'));
    }

    public function testInheritancePullsParentProperties(): void
    {
        // The child sets only price; title and type come from the parent section.
        $this->write('content/products/teapot/item.yaml', "price: 1200\ninherit: true\n");

        $item = $this->storage()->load('products/teapot');

        self::assertSame('Products', $item->title());
        self::assertSame('section', $item->type());
        self::assertSame(1200, $item->get('price'));
    }

    public function testMediaListsAttachments(): void
    {
        $this->write('content/products/teapot/photo.jpg', 'binary');

        self::assertContains('photo.jpg', $this->storage()->media('products/teapot'));
    }
}
