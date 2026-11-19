<?php
/**
 * The HTML document shell: doctype, head and body. The page content goes in
 * directly, so the header band, the sections and the footer band are siblings
 * and the page structure stays flat. Pages that carry their own header/footer
 * sections render them; the others fall back to the theme partials.
 *
 * @var string $content
 * @var string $title
 * @var \FlatCms\Content\Item|null $item
 * @var \FlatCms\Http\TemplateContext $this
 */
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'FlatCMS') ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/main.css')) ?>">
</head>
<body>
<?= $this->page($item ?? null, $content ?? '') ?>
</body>
</html>
