<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Core\Events;
use FlatCms\Modules\Builder\Types\GlobalBlockType;

/**
 * Turns the "layout" property of an item into HTML by rendering each block with
 * its type template. Blocks live inside columns, columns inside sections, so the
 * output mirrors the Gantry 5 grid. Falls back to an empty string with no blocks.
 *
 * Sections may carry a structural role: a "header" section becomes the site
 * header band and a "footer" section the footer band. Both sit at the top level
 * of the page next to the ordinary sections, which is what keeps a page flat.
 *
 * A section or block may also reference a global from GlobalLibrary, so one
 * header edited once renders on every page that uses it.
 */
final class LayoutRenderer
{
    public function __construct(
        private readonly App $app,
        private readonly TypeRegistry $types,
        private readonly Events $events,
        private readonly ?GlobalLibrary $globals = null,
    ) {
    }

    public function render(Item $item): string
    {
        return $this->renderBody($item);
    }

    /**
     * The page body: every non-chrome section, in order.
     */
    public function renderBody(Item $item): string
    {
        return $this->renderLayout($this->layoutOf($item), ['item' => $item]);
    }

    /**
     * A whole page: header band, body sections, footer band.
     */
    public function renderPage(Item $item): string
    {
        return $this->pageShell($item, $this->renderBody($item));
    }

    /**
     * Wrap an already rendered body in the site chrome. Templates that add their
     * own content (a child list, a cart table) use this to keep the same header
     * and footer.
     *
     * The header and footer are sections and nothing else: a page without a
     * header section renders without a header, and there is no theme fallback.
     * That keeps the builder the single source of truth for the page chrome, so
     * deleting the header in the editor removes it from the site.
     *
     * A template with no content item behind it (a cart, a contact form) borrows
     * the bands from the home page, so those pages match the rest of the site.
     * An element (a product, a blog post) has no layout either, so it borrows
     * them too and reads as part of the same site.
     */
    public function pageShell(?Item $item, string $body): string
    {
        $own = $item === null ? null : $this->resolveLayout($this->layoutOf($item));
        $header = $own?->withRole('header');
        $footer = $own?->withRole('footer');

        // A real page never borrows: its own sections are the whole truth.
        $borrows = $item === null || $this->app->schema->isElement($item->type());
        if ($borrows) {
            $home = $this->app->storage->load('index');
            if ($home !== null) {
                $shared = $this->resolveLayout($this->layoutOf($home));
                $header = $shared->withRole('header');
                $footer = $shared->withRole('footer');
            }
        }

        $data = ['item' => $item];

        return ($header !== null ? $this->renderSection($header, $data) : '')
            . $body
            . ($footer !== null ? $this->renderSection($footer, $data) : '');
    }

    /**
     * Render either an Item (using its "layout" property) or a raw layout array
     * (for callers that hold a layout outside the content tree).
     */
    public function renderValue(mixed $value): string
    {
        if ($value instanceof Item) {
            return $this->render($value);
        }

        $layout = Layout::fromValue(is_array($value) ? $value : [], $this->columns());
        if ($layout->isEmpty()) {
            return '';
        }

        return $this->renderLayout($layout);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderLayout(Layout $layout, array $data = []): string
    {
        $html = '';
        foreach ($this->resolveLayout($layout)->ordered() as $section) {
            // Bands are rendered by pageShell, not in the middle of the body.
            if ($section->role() !== '') {
                continue;
            }
            $html .= $this->renderSection($section, $data);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderSection(Section $section, array $data = []): string
    {
        $section = $this->resolveSection($section);

        $role = $section->role();
        $tag = match ($role) {
            'header' => 'header',
            'footer' => 'footer',
            default => 'section',
        };
        $prefix = $role === '' ? 'g-section' : 'site-' . $role;

        $classes = [$prefix];
        if (($variant = (string) $section->get('variant', '')) !== '') {
            $classes[] = $prefix . '--' . preg_replace('/[^a-z0-9-]/', '', $variant);
        }

        $style = [];
        if (($bg = (string) $section->get('background', '')) !== '') {
            $style[] = 'background:' . $bg;
        }
        if (($padding = (string) $section->get('padding', '')) !== '') {
            $style[] = '--g-padding:' . $padding;
        }
        if (($justify = Structure::justify($section->get('justify'))) !== '') {
            $style[] = '--g-justify:' . $justify;
        }
        if (($align = Structure::align($section->get('align'))) !== '') {
            $style[] = '--g-align:' . $align;
        }
        $styleAttr = $style === [] ? '' : ' style="' . htmlspecialchars(implode(';', $style), ENT_QUOTES) . '"';

        $container = (string) $section->get('width', 'boxed');
        $wrapperClass = $container === 'full' ? 'g-container g-container--full' : 'g-container';

        $inner = '';
        foreach ($section->rows() as $row) {
            $inner .= $this->renderRow($row, $data);
        }

        return '<' . $tag . ' class="' . Structure::classes(implode(' ', $classes), $section->cssClass()) . '"'
            . $styleAttr . '><div class="' . $wrapperClass . '">' . $inner . '</div></' . $tag . '>';
    }

    /**
     * A row is a flex line of columns. Its own justify/align control how those
     * columns sit on the line, which is the horizontal axis here.
     *
     * @param array<string, mixed> $data
     */
    public function renderRow(Row $row, array $data = []): string
    {
        $inner = '';
        foreach ($row->columns() as $column) {
            $inner .= $this->renderColumn($column, $data);
        }

        return '<div class="' . Structure::classes('g-row', $row->cssClass()) . '"'
            . Structure::style($row->settings()) . '>' . $inner . '</div>';
    }

    /**
     * A column is a vertical stack of blocks, so its justify/align work on the
     * vertical axis.
     *
     * @param array<string, mixed> $data
     */
    public function renderColumn(Column $column, array $data = []): string
    {
        $width = $column->width() > 0 ? $column->width() : $this->columns();

        $inner = '';
        foreach ($column->blocks() as $block) {
            $inner .= $this->renderBlock($block, $data);
        }

        return '<div class="' . Structure::classes('g-col', $column->cssClass()) . '"'
            . ' style="--g-span:' . $width . (($align = Structure::align($column->get('align'))) !== '' ? ';--g-align:' . $align : '')
            . (($justify = Structure::justify($column->get('justify'))) !== '' ? ';--g-justify:' . $justify : '')
            . '">' . $inner . '</div>';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderBlock(Block $block, array $data = []): string
    {
        if ($block->type === GlobalBlockType::NAME) {
            $block = $this->resolveGlobalBlock($block);
            if ($block === null) {
                return '';
            }
        }

        $type = $this->types->get($block->type);
        if ($type === null) {
            return '<!-- unknown block: ' . htmlspecialchars($block->type, ENT_QUOTES) . ' -->';
        }

        $data['block'] = $block;
        $data['settings'] = $type->withDefaults($block->settings);
        $data['renderer'] = $this;
        $this->events->emit('builder.block.before', $block, $type);

        $html = $this->app->renderer->renderTemplate($type->template(), $data);

        // A block's own class goes on a wrapper, because each particle template
        // owns its root tag and they differ (h2, nav, figure, section...).
        if ($block->cssClass !== '' && trim($html) !== '') {
            return '<div class="' . Structure::classes('g-block', $block->cssClass()) . '">' . $html . '</div>';
        }

        return $html;
    }

    public function globals(): ?GlobalLibrary
    {
        return $this->globals;
    }

    /**
     * Resolve every global reference in a layout, so roles and column content
     * come from the library before anything is rendered or ordered.
     */
    private function resolveLayout(Layout $layout): Layout
    {
        $resolved = [];
        foreach ($layout->sections() as $section) {
            $resolved[] = $this->resolveSection($section);
        }

        return new Layout($resolved, $this->columns());
    }

    /**
     * Swap a global reference for the stored section, letting the referencing
     * page override settings so it can restyle the shared band locally.
     */
    private function resolveSection(Section $section): Section
    {
        $name = trim((string) $section->get('global', ''));
        if ($name === '' || $this->globals === null) {
            return $section;
        }

        $shared = $this->globals->section($name);
        if ($shared === null) {
            return $section;
        }

        $settings = $shared->settings();
        foreach ($section->settings() as $key => $value) {
            if ($key !== 'global' && $value !== '' && $value !== null) {
                $settings[$key] = $value;
            }
        }
        if (($settings['role'] ?? '') === '') {
            $settings['role'] = $shared->get('role', '');
        }

        // The reference keeps its own title/class so a page can label the band
        // locally without editing the shared copy.
        return new Section(
            $shared->rows(),
            $settings,
            $section->id(),
            $section->title() !== '' ? $section->title() : $shared->title(),
            Structure::classes($shared->cssClass(), $section->cssClass()),
        );
    }

    private function resolveGlobalBlock(Block $block): ?Block
    {
        $name = trim((string) $block->get('name', ''));
        if ($name === '' || $this->globals === null) {
            return null;
        }

        return $this->globals->block($name);
    }

    private function layoutOf(Item $item): Layout
    {
        return Layout::fromValue($item->get('layout'), $this->columns());
    }

    private function columns(): int
    {
        return max(1, (int) $this->app->config->get('builder.grid_columns', 12));
    }
}
