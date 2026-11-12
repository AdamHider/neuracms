<?php
/**
 * Site chrome. The theme ships its own header and footer partials. A page may
 * also carry its own "Шапка сайта"/"Подвал сайта" builder sections (see the
 * builder section presets); those render inside the page body like any section.
 *
 * @var \FlatCms\App $app
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
$navigation = $app->query()->kind(\FlatCms\Content\Schema::KIND_PAGE)->sort('order')->limit(10)->find();
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
<?= $this->partial('header', ['site' => $site, 'navigation' => $navigation]) ?>
<main class="container">
<?= $content ?? '' ?>
</main>
<?= $this->partial('footer', ['site' => $site]) ?>
</body>
</html>
