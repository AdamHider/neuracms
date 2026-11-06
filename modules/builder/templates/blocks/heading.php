<?php
/**
 * @var \FlatCms\Modules\Builder\Block $block
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
$level = in_array($settings['level'] ?? 'h2', ['h1', 'h2', 'h3'], true) ? $settings['level'] : 'h2';
?>
<<?= $level ?> class="block block--heading"><?= $this->e($settings['text'] ?? '') ?></<?= $level ?>>
