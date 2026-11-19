<?php
/**
 * A product is an element, so it keeps its markdown body and gets a plain page
 * shell around it. Product pages are flat like any other page.
 *
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<article class="g-section">
    <div class="g-container">
        <h1><?= $this->e($item->title()) ?></h1>
        <?php if ($item->get('price') !== null): ?>
            <p class="price"><?= $this->e(number_format((float) $item->get('price'), 0, '.', ' ')) ?> ₽</p>
        <?php endif; ?>
        <?= $item->html() ?>
        <?= $this->layout($item) ?>

        <?php foreach ($item->media() as $media): ?>
            <img class="item__media" src="<?= $this->e('/media/' . $item->id() . '/' . $media) ?>" alt="<?= $this->e($media) ?>">
        <?php endforeach; ?>

        <?php if ($app->module('shop') !== null): ?>
            <form method="post" action="/cart/add">
                <input type="hidden" name="sku" value="<?= $this->e((string) $item->get('sku', $item->id())) ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit">В корзину</button>
            </form>
        <?php endif; ?>
    </div>
</article>
<?php
$body = ob_get_clean();
echo $this->partial('layout', ['content' => $body, 'item' => $item, 'title' => $item->title()]);
