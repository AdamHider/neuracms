<?php
/**
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
?>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <?= $this->e($site['title'] ?? 'FlatCMS') ?>. Работает на FlatCMS.</p>
    </div>
</footer>
