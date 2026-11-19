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
     * With no item (a cart, a contact form) the chrome comes from the home page,
     * so a header promoted to a global there applies to those pages too. A band
     * comes from the page's own header/footer section when it has one and from
     * the theme partials otherwise, so a page never loses its navigation.
     */
    public function pageShell(?Item $item, string $body): string
    {
        $chrome = $item ?? $this->app->storage->load('index');
        if ($chrome === null) {
            return $this->themeChrome('header', null) . $body . $this->themeChrome('footer', null);
        }

        $layout = $this->resolveLayout($this->layoutOf($chrome));
        $data = ['item' => $chrome];

        $header = $layout->withRole('header');
        $footer = $layout->withRole('footer');

        $head = $header !== null
            ? $this->renderSection($header, $data)
            : $this->themeChrome('header', $chrome);
        $foot = $footer !== null
            ? $this->renderSection($footer, $data)
            : $this->themeChrome('footer', $chrome);

        return $head . $body . $foot;
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

        $grid = $this->columns();
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
        $styleAttr = $style === [] ? '' : ' style="' . htmlspecialchars(implode(';', $style), ENT_QUOTES) . '"';

        $inner = '';
        foreach ($section->columns() as $column) {
            $width = $column->width() > 0 ? $column->width() : $grid;
            $inner .= '<div class="g-col" style="--g-span:' . $width . '">';
            foreach ($column->blocks() as $block) {
                $inner .= $this->renderBlock($block, $data);
            }
            $inner .= '</div>';
        }

        $container = (string) $section->get('width', 'boxed');
        $wrapperClass = $container === 'full' ? 'g-container g-container--full' : 'g-container';

        return '<' . $tag . ' class="' . implode(' ', $classes) . '"' . $styleAttr . '>'
            . '<div class="' . $wrapperClass . '"><div class="g-row">' . $inner . '</div></div>'
            . '</' . $tag . '>';
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

        return $this->app->renderer->renderTemplate($type->template(), $data);
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

        return new Section($shared->columns(), $settings, $section->id());
    }

    private function resolveGlobalBlock(Block $block): ?Block
    {
        $name = trim((string) $block->get('name', ''));
        if ($name === '' || $this->globals === null) {
            return null;
        }

        return $this->globals->block($name);
    }

    /**
     * Theme header/footer for pages that do not define their own band.
     */
    private function themeChrome(string $name, ?Item $item): string
    {
        $navigation = $this->app->query()
            ->kind(\FlatCms\Content\Schema::KIND_PAGE)
            ->sort('order')
            ->limit(10)
            ->find();

        return $this->app->renderer->partial($name, [
            'navigation' => $navigation,
            'item' => $item,
        ]);
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
