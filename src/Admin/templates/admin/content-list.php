<?php
/**
 * @var array $items
 * @var string $type
 * @var string|null $kind
 * @var array $types
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$types = $types ?? [];
$type = $type ?? '';
$kind = $kind ?? null;
$current = $type !== '' ? ($types[$type] ?? $type) : '';
$listHref = $kind === 'page' ? '/admin/pages' : ($kind === 'element' ? '/admin/elements' : '/admin/content');
$newParams = [];
if ($kind !== null) {
    $newParams['kind'] = $kind;
}
if ($type !== '') {
    $newParams['type'] = $type;
}
$newHref = '/admin/content/new' . ($newParams !== [] ? '?' . http_build_query($newParams) : '');
ob_start();
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-primary btn-sm" href="<?= $this->e($this->url($newHref)) ?>"><?= $this->icon('add') ?> Добавить<?= $type !== '' ? ': ' . $this->e($types[$type] ?? $type) : ($kind === 'page' ? ' страницу' : ' элемент') ?></a>

    <form method="get" action="<?= $this->e($this->url($listHref)) ?>" class="d-flex gap-2 ms-auto">
        <div class="input-group input-group-sm" style="width:auto">
            <span class="input-group-text"><?= $this->icon('filter') ?></span>
            <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                <option value="">Все типы</option>
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= $this->e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="btn btn-outline-secondary btn-sm" type="submit"><?= $this->icon('search') ?></button></noscript>
    </form>
</div>

<?php if ($current !== ''): ?>
    <p class="muted">Фильтр: <span class="badge text-bg-light"><?= $this->e($current) ?></span>
        <a href="<?= $this->e($this->url($listHref)) ?>">сбросить</a></p>
<?php endif; ?>

<div class="panel p-0">
<table class="table table-hover align-middle mb-0">
    <thead>
    <tr><th>ID</th><th>Заголовок</th><th>Тип</th><th>Статус</th><th class="text-end">Действия</th></tr>
    </thead>
    <tbody>
    <?php if ($items === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Ничего не найдено.</td></tr>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
        <?php $isPage = $app->schema->isPage($item->type()); ?>
        <tr>
            <td><code><?= $this->e($item->id()) ?></code></td>
            <td><?= $this->e($item->title()) ?></td>
            <td><span class="badge text-bg-light"><?= $this->e($types[$item->type()] ?? $item->type()) ?></span></td>
            <td>
                <?php if ($item->published()): ?>
                    <span class="badge text-bg-success"><?= $this->icon('check') ?> опубл.</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">черновик</span>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                    <a class="btn btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($item->id()))) ?>" title="Изменить"><?= $this->icon('edit') ?></a>
                    <?php if ($isPage && $app->module('builder') !== null): ?>
                        <a class="btn btn-outline-secondary" href="<?= $this->e($this->url('/admin/builder?id=' . rawurlencode($item->id()))) ?>" title="Конструктор"><?= $this->icon('builder') ?></a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/history?id=' . rawurlencode($item->id()))) ?>" title="История"><?= $this->icon('history') ?></a>
                    <a class="btn btn-outline-secondary" href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener" title="Открыть"><?= $this->icon('view') ?></a>
                </div>
                <form method="post" action="<?= $this->e($this->url('/admin/content/delete')) ?>" class="d-inline"
                      onsubmit="return confirm('Удалить элемент?');">
                    <input type="hidden" name="id" value="<?= $this->e($item->id()) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить"><?= $this->icon('delete') ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$content = ob_get_clean();
$listTitle = $kind === 'page' ? 'Страницы' : ($kind === 'element' ? 'Элементы' : 'Контент');
$activeNav = $kind === 'page' ? 'pages' : ($kind === 'element' ? 'elements' : 'content');
// A type that asked for its own sidebar entry highlights that entry when filtered.
if ($type !== '' && !empty($app->schema->get($type)['admin']['menu'])) {
    $activeNav = 'type:' . $type;
    $listTitle = $types[$type] ?? $type;
}
echo $this->partial('admin/layout', ['content' => $content, 'title' => $listTitle, 'active' => $activeNav, 'admin_user' => $admin_user ?? null]);
