<?php
/**
 * @var array $counts
 * @var array $recent
 * @var array $orders
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$icons = [
    'page' => 'content',
    'section' => 'section',
    'product' => 'products',
    'category' => 'types',
    'menu-item' => 'link',
    'post' => 'text',
];
ob_start();
?>
<section class="cards mb-4">
    <?php foreach ($counts as $type => $count): ?>
        <a class="card" href="<?= $this->e($this->url('/admin/content?type=' . rawurlencode((string) $type))) ?>">
            <span class="card__icon"><?= $this->icon($icons[$type] ?? 'content') ?></span>
            <span class="card__count"><?= (int) $count ?></span>
            <span class="card__label"><?= $this->e($type) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-primary btn-sm" href="<?= $this->e($this->url('/admin/content/new')) ?>"><?= $this->icon('add') ?> Новый элемент</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $this->e($this->url('/admin/types')) ?>"><?= $this->icon('types') ?> Типы контента</a>
    <?php if ($app->module('builder') !== null): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= $this->e($this->url('/admin/content')) ?>"><?= $this->icon('builder') ?> Конструктор</a>
    <?php endif; ?>
</div>

<h2><?= $this->icon('history') ?> Последние изменения</h2>
<div class="panel p-0">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Заголовок</th><th>Тип</th><th>Адрес</th><th class="text-end"></th></tr></thead>
    <tbody>
    <?php if ($recent === []): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">Контента пока нет.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $item): ?>
        <tr>
            <td><?= $this->e($item->title()) ?></td>
            <td><span class="badge text-bg-light"><?= $this->e($item->type()) ?></span></td>
            <td><a href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener"><?= $this->e($item->url()) ?></a></td>
            <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($item->id()))) ?>"><?= $this->icon('edit') ?> Изменить</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($orders !== []): ?>
    <h2><?= $this->icon('shop') ?> Последние заказы</h2>
    <div class="panel p-0">
    <table class="table align-middle mb-0">
        <thead><tr><th>Номер</th><th>Статус</th><th>Сумма</th><th>Дата</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $this->e($order->id) ?></td>
                <td><span class="badge text-bg-light"><?= $this->e($order->status) ?></span></td>
                <td><?= $this->e(number_format($order->total, 0, '.', ' ')) ?> <?= $this->e($order->currency) ?></td>
                <td class="muted"><?= $this->e($order->createdAt) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Панель', 'active' => 'dashboard', 'admin_user' => $admin_user ?? null]);
