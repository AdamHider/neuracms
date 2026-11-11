<?php
/**
 * Standalone login page (no admin shell).
 *
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — FlatCMS</title>
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->ownerAsset('admin', 'css/admin.css')) ?>">
</head>
<body class="admin admin--login">
<main class="login">
    <h1><?= $this->icon('dashboard', 'text-primary') ?> FlatCMS</h1>
    <p class="muted" style="margin-top:-.5rem">Вход в панель управления</p>

    <?php if (($error ?? '') !== ''): ?>
        <p class="error"><?= $this->e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= $this->e($this->url('/admin/login')) ?>">
        <label>Логин <input type="text" name="login" autocomplete="username" required autofocus></label>
        <label>Пароль <input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit" class="btn btn-primary w-100"><?= $this->icon('login') ?> Войти</button>
    </form>
</main>
</body>
</html>
