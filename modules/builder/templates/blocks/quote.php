<?php
/**
 * @var array $settings
 * @var \FlatCms\Http\TemplateContext $this
 */
?>
<blockquote class="block block--quote">
    <p><?= $this->e($settings['text'] ?? '') ?></p>
    <?php if (($settings['author'] ?? '') !== ''): ?>
        <cite><?= $this->e($settings['author']) ?></cite>
    <?php endif; ?>
</blockquote>
