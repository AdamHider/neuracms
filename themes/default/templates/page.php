<?php
/**
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$layout = $this->layout($item);
ob_start();
?>
<article class="item item--<?= $this->e($item->type()) ?>">
    <h1><?= $this->e($item->title()) ?></h1>
    <?= $layout !== '' ? $layout : $item->html() ?>
</article>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'item' => $item, 'title' => $item->title()]);
