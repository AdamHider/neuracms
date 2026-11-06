<?php
/**
 * @var \FlatCms\Modules\Builder\Block $block
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
$html = (new \FlatCms\Content\Markdown())->parse((string) ($settings['body'] ?? ''));
?>
<div class="block block--text"><?= $html ?></div>
