<?php
/**
 * @var array $site
 * @var list<\FlatCms\Content\Item> $navigation
 * @var \FlatCms\Http\TemplateContext $this
 */
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-header__brand" href="<?= $this->e($this->url('/')) ?>">
            <?= $this->e($site['title'] ?? 'FlatCMS') ?>
        </a>
        <nav class="site-nav">
            <?php foreach (($navigation ?? []) as $navItem): ?>
                <a href="<?= $this->e($this->url($navItem->url())) ?>"><?= $this->e($navItem->title()) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
