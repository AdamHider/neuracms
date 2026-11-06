<?php
/**
 * @var \FlatCms\Modules\Builder\Block $block
 * @var array $settings
 * @var \FlatCms\Modules\Builder\LayoutRenderer $renderer
 * @var \FlatCms\Http\TemplateContext $this
 */
$renderer = $renderer ?? null;
$ratio = in_array($settings['ratio'] ?? '6-6', ['6-6', '8-4', '4-8'], true) ? $settings['ratio'] : '6-6';
[$left, $right] = array_map('intval', explode('-', $ratio));

$render = static function (mixed $blocks) use ($renderer): string {
    if ($renderer === null || !is_array($blocks)) {
        return '';
    }
    $out = '';
    foreach ($blocks as $child) {
        if (is_array($child) && isset($child['type'])) {
            $out .= $renderer->renderBlock(\FlatCms\Modules\Builder\Block::fromArray($child));
        }
    }
    return $out;
};
?>
<div class="block block--columns" style="--left:<?= $left ?>;--right:<?= $right ?>">
    <div class="block__col"><?= $render($settings['left'] ?? []) ?></div>
    <div class="block__col"><?= $render($settings['right'] ?? []) ?></div>
</div>
