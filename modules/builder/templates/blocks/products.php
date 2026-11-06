<?php
/**
 * @var \FlatCms\Modules\Builder\Block $block
 * @var array $settings
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$limit = max(1, (int) ($settings['limit'] ?? 6));
$tags = array_filter(array_map('trim', explode(',', (string) ($settings['tags'] ?? ''))));

$query = $app->query()->type('product')->sort('order')->limit($limit);
$products = $query->find();

if ($tags !== []) {
    $products = array_values(array_filter($products, static function ($item) use ($tags): bool {
        $itemTags = $item->get('tags', []);
        return is_array($itemTags) && array_intersect($tags, $itemTags) !== [];
    }));
}
?>
<section class="block block--products">
    <?php if (($settings['title'] ?? '') !== ''): ?>
        <h2><?= $this->e($settings['title']) ?></h2>
    <?php endif; ?>
    <?php if ($products === []): ?>
        <p class="muted">Товары пока не добавлены.</p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <h3><a href="<?= $this->e($this->url($product->url())) ?>"><?= $this->e($product->title()) ?></a></h3>
                    <?php if ($product->get('price') !== null): ?>
                        <p class="price"><?= $this->e(number_format((float) $product->get('price'), 0, '.', ' ')) ?> ₽</p>
                    <?php endif; ?>
                    <?php if ($app->module('shop') !== null): ?>
                        <form method="post" action="<?= $this->e($this->url('/cart/add')) ?>">
                            <input type="hidden" name="sku" value="<?= $this->e((string) $product->get('sku', $product->id())) ?>">
                            <input type="hidden" name="qty" value="1">
                            <button type="submit">В корзину</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
