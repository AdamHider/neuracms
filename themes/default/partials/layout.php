<?php
/**
 * @var \FlatCms\App $app
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? $site['title'] ?? 'FlatCMS') ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/main.css')) ?>">
</head>
<body>
<?php $navigation = $app->query()->type('page')->sort('order')->limit(10)->find(); ?>
<?= $this->partial('header', ['site' => $site, 'navigation' => $navigation]) ?>
<main class="container">
<?= $content ?? '' ?>
</main>
<?= $this->partial('footer', ['site' => $site]) ?>
</body>
</html>
