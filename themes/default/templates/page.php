<?php
/**
 * A page's appearance comes entirely from the builder layout. With no blocks the
 * page renders empty — the builder is the single source of truth for pages.
 *
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$layout = $this->layout($item);
ob_start();
?>
<article class="item item--<?= $this->e($item->type()) ?>">
    <?= $layout ?>
</article>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'item' => $item, 'title' => $item->title()]);
