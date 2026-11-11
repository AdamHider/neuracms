<?php
/**
 * @var array $counts
 * @var array $recent
 * @var array $orders
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<section class="cards">
    <?php foreach ($counts as $type => $count): ?>
        <a class="card" href="<?= $this->e($this->url('/admin/content?type=' . rawurlencode((string) $type))) ?>">
            <span class="card__count"><?= (int) $count ?></span>
            <span class="card__label"><?= $this->e($type) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<h2>Последние изменения</h2>
<table class="table">
    <thead><tr><th>Заголовок</th><th>Тип</th><th>Адрес</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $item): ?>
        <tr>
            <td><?= $this->e($item->title()) ?></td>
            <td><?= $this->e($item->type()) ?></td>
            <td><a href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener"><?= $this->e($item->url()) ?></a></td>
            <td><a href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($item->id()))) ?>">Изменить</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($orders !== []): ?>
    <h2>Последние заказы</h2>
    <table class="table">
        <thead><tr><th>Номер</th><th>Статус</th><th>Сумма</th><th>Дата</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $this->e($order->id) ?></td>
                <td><?= $this->e($order->status) ?></td>
                <td><?= $this->e(number_format($order->total, 0, '.', ' ')) ?> <?= $this->e($order->currency) ?></td>
                <td><?= $this->e($order->createdAt) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Панель', 'admin_user' => $admin_user ?? null]);
