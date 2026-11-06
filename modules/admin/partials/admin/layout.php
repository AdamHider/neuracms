<?php
/**
 * Admin shell: top bar, sidebar and content area.
 *
 * @var string $content
 * @var string $title
 * @var array|null $admin_user
 * @var \FlatCms\Http\TemplateContext $this
 */
$user = $admin_user ?? null;
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Админка') ?> — FlatCMS</title>
    <link rel="stylesheet" href="<?= $this->e($this->asset('admin/css/admin.css')) ?>">
</head>
<body class="admin">
<header class="admin__top">
    <a class="admin__brand" href="<?= $this->e($this->url('/admin')) ?>">FlatCMS</a>
    <div class="admin__user">
        <?php if ($user !== null): ?>
            <span><?= $this->e($user['login']) ?> · <?= $this->e($user['role']) ?></span>
            <a href="<?= $this->e($this->url('/')) ?>" target="_blank" rel="noopener">Открыть сайт</a>
            <form method="post" action="<?= $this->e($this->url('/admin/logout')) ?>">
                <button type="submit">Выйти</button>
            </form>
        <?php endif; ?>
    </div>
</header>
<div class="admin__body">
    <?php if ($user !== null): ?>
        <nav class="admin__nav">
            <a href="<?= $this->e($this->url('/admin')) ?>">Панель</a>
            <a href="<?= $this->e($this->url('/admin/content')) ?>">Контент</a>
            <a href="<?= $this->e($this->url('/admin/content/new')) ?>">Добавить элемент</a>
            <?php if ($isAdmin): ?>
                <a href="<?= $this->e($this->url('/admin/users')) ?>">Пользователи</a>
                <a href="<?= $this->e($this->url('/admin/tools')) ?>">Инструменты</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
    <main class="admin__main">
        <h1><?= $this->e($title ?? '') ?></h1>
        <?= $content ?>
    </main>
</div>
</body>
</html>
