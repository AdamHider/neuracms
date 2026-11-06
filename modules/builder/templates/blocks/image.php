<?php
/**
 * @var \FlatCms\Modules\Builder\Block $block
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
$src = (string) ($settings['src'] ?? '');
$width = max(10, min(100, (int) ($settings['width'] ?? 100)));
if ($src === '') {
    return;
}
$url = preg_match('#^https?://#', $src) === 1 ? $src : $this->url($src);
?>
<figure class="block block--image" style="--width:<?= $width ?>%">
    <img src="<?= $this->e($url) ?>" alt="<?= $this->e($settings['alt'] ?? '') ?>" loading="lazy">
    <?php if (($settings['alt'] ?? '') !== ''): ?>
        <figcaption><?= $this->e($settings['alt']) ?></figcaption>
    <?php endif; ?>
</figure>
