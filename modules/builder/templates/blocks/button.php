<?php
/**
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
$style = ($settings['style'] ?? 'primary') === 'outline' ? 'button--outline' : 'button--primary';
?>
<p class="block block--button">
    <a class="button <?= $style ?>" href="<?= $this->e($this->url((string) ($settings['url'] ?? '/'))) ?>">
        <?= $this->e($settings['text'] ?? '') ?>
    </a>
</p>
