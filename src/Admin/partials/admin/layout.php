<?php
/**
 * Admin shell: top bar, sidebar and content area. Built on Bootstrap 5 plus a
 * small admin.css layer, with Bootstrap Icons for glyphs.
 *
 * @var string $content
 * @var string $title
 * @var array|null $admin_user
 * @var string $active   Nav key of the current page (for highlighting)
 * @var \FlatCms\Http\TemplateContext $this
 */
$user = $admin_user ?? null;
$isAdmin = ($user['role'] ?? '') === 'admin';
$active = $active ?? '';
$nav = [
    ['key' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Панель', 'href' => '/admin'],
    ['key' => 'pages', 'icon' => 'content', 'label' => 'Страницы', 'href' => '/admin/pages'],
    ['key' => 'elements', 'icon' => 'types', 'label' => 'Элементы', 'href' => '/admin/elements'],
    ['key' => 'types', 'icon' => 'settings', 'label' => 'Типы контента', 'href' => '/admin/types'],
];
$adminNav = [
    ['key' => 'users', 'icon' => 'users', 'label' => 'Пользователи', 'href' => '/admin/users'],
    ['key' => 'tools', 'icon' => 'tools', 'label' => 'Инструменты', 'href' => '/admin/tools'],
];
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Админка') ?> — FlatCMS</title>
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'css/admin.css')) ?>">
    <?= $head ?? '' ?>
</head>
<body class="admin" data-bs-theme="light">
<?php if ($user !== null): ?>
<header class="admin__top">
    <button class="admin__burger btn btn-sm" type="button" data-admin-toggle="nav" aria-label="Меню">
        <i class="bi bi-list"></i>
    </button>
    <a class="admin__brand" href="<?= $this->e($this->url('/admin')) ?>">
        <i class="bi bi-layers-half"></i> FlatCMS
    </a>
    <div class="admin__user dropdown">
        <button class="admin__user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="admin__avatar"><?= $this->e(mb_strtoupper(mb_substr((string) $user['login'], 0, 1))) ?></span>
            <span class="admin__user-name"><?= $this->e($user['login']) ?></span>
            <span class="badge text-bg-light"><?= $this->e($user['role']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $this->e($this->url('/')) ?>" target="_blank" rel="noopener"><?= $this->icon('view') ?> Открыть сайт</a></li>
            <?php if ($isAdmin): ?>
                <li><a class="dropdown-item" href="<?= $this->e($this->url('/admin/tools')) ?>"><?= $this->icon('tools') ?> Инструменты</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="<?= $this->e($this->url('/admin/logout')) ?>">
                    <button class="dropdown-item text-danger" type="submit"><?= $this->icon('logout') ?> Выйти</button>
                </form>
            </li>
        </ul>
    </div>
</header>
<?php endif; ?>
<div class="admin__body">
    <?php if ($user !== null): ?>
        <nav class="admin__nav" id="admin-nav">
            <?php foreach ($nav as $entry): ?>
                <a class="admin__nav-link<?= $active === $entry['key'] ? ' is-active' : '' ?>" href="<?= $this->e($this->url($entry['href'])) ?>">
                    <?= $this->icon($entry['icon']) ?><span><?= $this->e($entry['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($isAdmin): ?>
                <div class="admin__nav-sep">Администратор</div>
                <?php foreach ($adminNav as $entry): ?>
                    <a class="admin__nav-link<?= $active === $entry['key'] ? ' is-active' : '' ?>" href="<?= $this->e($this->url($entry['href'])) ?>">
                        <?= $this->icon($entry['icon']) ?><span><?= $this->e($entry['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
    <main class="admin__main">
        <h1><?= $this->e($title ?? '') ?></h1>
        <?= $content ?>
    </main>
</div>
<script src="<?= $this->e($this->ownerAsset('admin', 'vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= $this->e($this->ownerAsset('admin', 'js/admin.js')) ?>"></script>
</body>
</html>
