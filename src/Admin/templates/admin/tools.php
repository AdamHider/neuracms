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
<?php if (($done ?? '') !== ''): ?><p class="notice"><?= $this->icon('check') ?> Готово: <?= $this->e($done) ?></p><?php endif; ?>

<div class="panel p-0 mb-4">
<table class="table mb-0">
    <tr><th style="width:40%"><?= $this->icon('history') ?> Индекс собран</th><td><?= $this->e(date('Y-m-d H:i:s', $stamp)) ?></td></tr>
    <tr><th><?= $this->icon('grid') ?> Размер кеша</th><td><?= $this->e(number_format($cache_size / 1024, 1, '.', ' ')) ?> КБ</td></tr>
    <tr><th><?= $this->icon('history') ?> История</th><td><?= $this->e(number_format($history_size / 1024, 1, '.', ' ')) ?> КБ</td></tr>
    <tr><th><?= $this->icon('info') ?> PHP</th><td><?= $this->e(PHP_VERSION) ?></td></tr>
</table>
</div>

<div class="d-flex gap-2 flex-wrap">
    <form method="post" action="<?= $this->e($this->url('/admin/tools/rebuild')) ?>">
        <button type="submit" class="btn btn-primary"><?= $this->icon('grid') ?> Пересобрать индекс</button>
    </form>
    <form method="post" action="<?= $this->e($this->url('/admin/tools/clear-cache')) ?>">
        <button type="submit" class="btn btn-outline-danger"><?= $this->icon('delete') ?> Очистить кеш</button>
    </form>
</div>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Инструменты', 'active' => 'tools', 'admin_user' => $admin_user ?? null]);
