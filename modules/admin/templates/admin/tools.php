<?php
/**
 * @var int $stamp
 * @var int $cache_size
 * @var int $history_size
 * @var string $done
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<?php if (($done ?? '') !== ''): ?><p class="notice">Готово: <?= $this->e($done) ?></p><?php endif; ?>

<table class="table">
    <tr><th>Индекс собран</th><td><?= $this->e(date('Y-m-d H:i:s', $stamp)) ?></td></tr>
    <tr><th>Размер кеша</th><td><?= $this->e(number_format($cache_size / 1024, 1, '.', ' ')) ?> КБ</td></tr>
    <tr><th>История</th><td><?= $this->e(number_format($history_size / 1024, 1, '.', ' ')) ?> КБ</td></tr>
    <tr><th>PHP</th><td><?= $this->e(PHP_VERSION) ?></td></tr>
</table>

<form method="post" action="<?= $this->e($this->url('/admin/tools/rebuild')) ?>">
    <button type="submit">Пересобрать индекс</button>
</form>
<form method="post" action="<?= $this->e($this->url('/admin/tools/clear-cache')) ?>">
    <button type="submit">Очистить кеш</button>
</form>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Инструменты', 'admin_user' => $admin_user ?? null]);
