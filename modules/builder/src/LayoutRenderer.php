<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Core\Events;

/**
 * Turns the "layout" property of an item into HTML by rendering each block with
 * its type template. Blocks live inside columns, columns inside sections, so the
 * output mirrors the Gantry 5 grid. Falls back to an empty string with no blocks.
 */
final class LayoutRenderer
{
    public function __construct(
        private readonly App $app,
        private readonly TypeRegistry $types,
        private readonly Events $events,
    ) {
    }

    public function render(Item $item): string
    {
        $layout = Layout::fromValue($item->get('layout'), $this->columns());
        if ($layout->isEmpty()) {
            return '';
        }

        return $this->renderLayout($layout, ['item' => $item]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderLayout(Layout $layout, array $data = []): string
    {
        $html = '';
        foreach ($layout->sections() as $section) {
            $html .= $this->renderSection($section, $data);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderSection(Section $section, array $data = []): string
    {
        $columns = $section->columns();
        $grid = $this->columns();
        $classes = ['g-section'];
        if (($variant = (string) $section->get('variant', '')) !== '') {
            $classes[] = 'g-section--' . preg_replace('/[^a-z0-9-]/', '', $variant);
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
        foreach ($columns as $column) {
            $width = $column->width() > 0 ? $column->width() : $grid;
            $inner .= '<div class="g-col" style="--g-span:' . $width . '">';
            foreach ($column->blocks() as $block) {
                $inner .= $this->renderBlock($block, $data);
            }
            $inner .= '</div>';
        }

        $container = (string) $section->get('width', 'boxed');
        $wrapperClass = $container === 'full' ? 'g-container g-container--full' : 'g-container';

        return '<section class="' . implode(' ', $classes) . '"' . $styleAttr . '>'
            . '<div class="' . $wrapperClass . '"><div class="g-row">' . $inner . '</div></div>'
            . '</section>';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderBlock(Block $block, array $data = []): string
    {
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

    private function columns(): int
    {
        return max(1, (int) $this->app->config->get('builder.grid_columns', 12));
    }
}
