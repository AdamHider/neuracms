<?php
/**
 * @var array $settings
 */
$size = in_array($settings['size'] ?? 'md', ['sm', 'md', 'lg', 'xl'], true) ? $settings['size'] : 'md';
?>
<div class="block block--spacer block--spacer-<?= $size ?>" aria-hidden="true"></div>
