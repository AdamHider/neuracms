<?php
/**
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
$src = (string) ($settings['src'] ?? '');
if ($src === '') {
    return;
}
$width = max(10, min(100, (int) ($settings['width'] ?? 100)));
$url = preg_match('#^(https?:)?//#', $src) === 1 ? $src : $this->url($src);
$alt = (string) ($settings['alt'] ?? '');
$caption = (string) ($settings['caption'] ?? '');
?>
<figure class="block block--media" style="--width:<?= $width ?>%">
    <img src="<?= $this->e($url) ?>" alt="<?= $this->e($alt) ?>" loading="lazy">
    <?php if ($caption !== ''): ?>
        <figcaption><?= $this->e($caption) ?></figcaption>
    <?php endif; ?>
</figure>
