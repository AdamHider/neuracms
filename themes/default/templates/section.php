<?php
/**
 * A section renders its builder layout and lists its child items. Like a page it
 * has no markdown body — the builder defines its appearance.
 *
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$children = $app->query()->children($item->id())->sort('order')->find();
$layout = $this->layout($item);
ob_start();
?>
<section class="item item--section">
    <?= $layout ?>
    <?php if ($children !== []): ?>
        <ul class="item-list">
            <?php foreach ($children as $child): ?>
                <li>
                    <a href="<?= $this->e($this->url($child->url())) ?>"><?= $this->e($child->title()) ?></a>
                    <?php $price = $child->get('price'); ?>
                    <?php if ($price !== null): ?>
                        <span class="price"><?= $this->e(number_format((float) $price, 0, '.', ' ')) ?> ₽</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'item' => $item, 'title' => $item->title()]);
