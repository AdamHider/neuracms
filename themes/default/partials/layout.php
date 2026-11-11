<?php
/**
 * Site chrome. The header and footer are theme regions: if the site defines a
 * layout for them (edited in the builder) it is rendered here, otherwise the
 * theme falls back to its own default header/footer partials.
 *
 * @var \FlatCms\App $app
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
$headerRegion = $this->region('header');
$footerRegion = $this->region('footer');
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
<?php if ($headerRegion !== ''): ?>
    <?= $headerRegion ?>
<?php else: ?>
    <?php $navigation = $app->query()->type('page')->sort('order')->limit(10)->find(); ?>
    <?= $this->partial('header', ['site' => $site, 'navigation' => $navigation]) ?>
<?php endif; ?>
<main class="container">
<?= $content ?? '' ?>
</main>
<?php if ($footerRegion !== ''): ?>
    <?= $footerRegion ?>
<?php else: ?>
    <?= $this->partial('footer', ['site' => $site]) ?>
<?php endif; ?>
</body>
</html>
