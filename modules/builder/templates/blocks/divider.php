<?php
/**
 * @var array $settings
 */
$style = in_array($settings['style'] ?? 'solid', ['solid', 'dashed', 'dotted'], true) ? $settings['style'] : 'solid';
?>
<hr class="block block--divider block--divider-<?= $style ?>">
