<?php
/**
 * @var \FlatCms\Modules\Shop\Order\Order $order
 * @var string $currency
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<section class="order">
    <h1>Заказ <?= $this->e($order->id) ?></h1>
    <p class="order__status">Статус: <?= $this->e($order->status) ?></p>

    <table class="order__lines">
        <?php foreach ($order->lines as $line): ?>
            <tr>
                <td><?= $this->e($line['title'] ?? '') ?> × <?= (int) ($line['qty'] ?? 0) ?></td>
                <td><?= $this->e(number_format((float) ($line['price'] ?? 0) * (int) ($line['qty'] ?? 0), 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="order__total">
            <td>Итого</td>
            <td><?= $this->e(number_format($order->total, 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
        </tr>
    </table>

    <p>Способ оплаты: <?= $this->e($order->provider) ?></p>
    <p>Оформлен: <?= $this->e($order->createdAt) ?></p>
</section>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'title' => 'Заказ ' . $order->id]);
