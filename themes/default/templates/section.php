<?php
/**
 * A section page renders like any other page, plus the list of its children.
 * Its own sections stay flat; the child list is appended to the body.
 *
 * @var \FlatCms\Content\Item $item
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$children = $app->query()->children($item->id())->sort('order')->find();
ob_start();
?>
<?= $this->layout($item) ?>
<?php if ($children !== []): ?>
    <section class="g-section">
        <div class="g-container">
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
        </div>
    </section>
<?php endif; ?>
<?php
$body = ob_get_clean();
echo $this->partial('layout', ['content' => $body, 'item' => $item, 'title' => $item->title()]);
