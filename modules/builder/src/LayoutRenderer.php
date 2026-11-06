<?php

declare(strict_types=1);

namespace FlatCms\Modules\Builder;

use FlatCms\App;
use FlatCms\Content\Item;
use FlatCms\Core\Events;

/**
 * Turns the "layout" property of an item into HTML by rendering each block with
 * its type template. Falls back to an empty string when a page has no blocks.
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
        $blocks = $layout->blocks();
        $html = '';
        $openRow = false;
        $rowSpans = [];
        $rowPos = 0;

        foreach ($blocks as $index => $block) {
            $rendered = $this->renderBlock($block, $data);

            if (!$layout->hasGrid()) {
                if ($openRow) {
                    $html .= '</div>';
                    $openRow = false;
                }
                $html .= $rendered;
                continue;
            }

            if (!$openRow || $rowPos >= count($rowSpans)) {
                if ($openRow) {
                    $html .= '</div>';
                }
                $rowSpans = $layout->rowFor($index) ?? [$layout->columns()];
                $rowPos = 0;
                $html .= '<div class="grid">';
                $openRow = true;
            }

            $width = (int) ($rowSpans[$rowPos] ?? $layout->columns());
            $html .= '<div class="grid__col" style="--span:' . $width . '">' . $rendered . '</div>';
            $rowPos++;
        }

        if ($openRow) {
            $html .= '</div>';
        }

        return $html;
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
