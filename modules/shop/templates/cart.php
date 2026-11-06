<?php
/**
 * @var array $lines
 * @var float $total
 * @var string $currency
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<section class="cart">
    <h1>Корзина</h1>

    <?php if ($lines === []): ?>
        <p>Корзина пуста.</p>
        <p><a href="<?= $this->e($this->url('/products')) ?>">Перейти в каталог</a></p>
    <?php else: ?>
        <form method="post" action="<?= $this->e($this->url('/cart/update')) ?>">
            <table class="cart__table">
                <thead>
                    <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($lines as $sku => $line): ?>
                    <tr>
                        <td><a href="<?= $this->e($this->url('/product/' . $line->itemId)) ?>"><?= $this->e($line->title) ?></a></td>
                        <td><?= $this->e(number_format($line->price, 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
                        <td><input type="number" name="qty[<?= $this->e($sku) ?>]" value="<?= (int) $line->qty ?>" min="0"></td>
                        <td><?= $this->e(number_format($line->total(), 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
                        <td>
                            <button type="submit" formaction="<?= $this->e($this->url('/cart/remove')) ?>" name="sku" value="<?= $this->e($sku) ?>">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3">Итого</td><td><?= $this->e(number_format($total, 0, '.', ' ')) ?> <?= $this->e($currency) ?></td><td></td></tr>
                </tfoot>
            </table>
            <button type="submit">Обновить</button>
        </form>
        <p><a class="button" href="<?= $this->e($this->url('/checkout')) ?>">Оформить заказ</a></p>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'title' => 'Корзина']);
