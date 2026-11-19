<?php
/**
 * @var array $site
 * @var list<\FlatCms\Content\Item> $navigation
 * @var \FlatCms\Http\TemplateContext $this
 */
// A module link ("Контакты", "Корзина") repeats a content page that already
// covers the same destination, so the page wins and the link is dropped. The
// label counts too: a site whose contacts page lives at /contacts should not
// show a second "Контакты" pointing at the module's /contact form.
$seen = [];
foreach (($navigation ?? []) as $navItem) {
    $seen[rtrim($this->url($navItem->url()), '/')] = true;
    $seen['label:' . mb_strtolower(trim($navItem->title()))] = true;
}
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
            <?php foreach (($site['nav'] ?? []) as $link): ?>
                <?php
                $href = rtrim($this->url($link['url']), '/');
                $label = 'label:' . mb_strtolower(trim((string) $link['title']));
                if (isset($seen[$href]) || isset($seen[$label])) {
                    continue;
                }
                ?>
                <a href="<?= $this->e($href) ?>"><?= $this->e($link['title']) ?></a>
            <?php endforeach; ?>
            <?php if (($site['cart_count'] ?? 0) > 0): ?>
                <span class="site-nav__cart"><?= (int) $site['cart_count'] ?></span>
            <?php endif; ?>
        </nav>
    </div>
</header>
