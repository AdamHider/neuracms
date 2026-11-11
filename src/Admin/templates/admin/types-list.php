<?php
/**
 * @var array<string, array{label: string, builtin: bool, fields: array, indexed: list<string>, relations: array, referenced_by: list<string>, count: int}> $types
 * @var string $created
 * @var string $deleted
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<?php if (($created ?? '') !== ''): ?><p class="notice"><?= $this->icon('check') ?> Тип «<?= $this->e($created) ?>» сохранён.</p><?php endif; ?>
<?php if (($deleted ?? '') !== ''): ?><p class="notice"><?= $this->icon('check') ?> Тип «<?= $this->e($deleted) ?>» удалён.</p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p><?php endif; ?>

<p><a class="btn btn-primary btn-sm" href="<?= $this->e($this->url('/admin/types/new')) ?>"><?= $this->icon('add') ?> Добавить тип</a></p>

<div class="panel p-0">
<table class="table table-hover align-middle mb-0">
    <thead>
    <tr>
        <th>Тип</th><th>Название</th><th>Поля</th><th>Связи</th><th>Элементов</th><th class="text-end">Действия</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($types as $name => $type): ?>
        <tr>
            <td>
                <code><?= $this->e($name) ?></code>
                <?php if (!empty($type['builtin'])): ?><span class="tag">встроенный</span><?php endif; ?>
            </td>
            <td><?= $this->e($type['label']) ?></td>
            <td><?= count($type['fields']) ?></td>
            <td>
                <?php foreach ($type['relations'] as $field => $relation): ?>
                    <div><code><?= $this->e($field) ?></code> → <?= $this->e($relation['to']) ?><?= $relation['multiple'] ? ' (много)' : '' ?></div>
                <?php endforeach; ?>
                <?php if (!empty($type['referenced_by'])): ?>
                    <div class="muted">Ссылаются: <?= $this->e(implode(', ', $type['referenced_by'])) ?></div>
                <?php endif; ?>
            </td>
            <td><a href="<?= $this->e($this->url('/admin/content?type=' . rawurlencode($name))) ?>"><?= (int) $type['count'] ?></a></td>
            <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/types/edit?name=' . rawurlencode($name))) ?>"><?= $this->icon('edit') ?> Изменить</a>
                <?php if (empty($type['builtin'])): ?>
                    <form method="post" action="<?= $this->e($this->url('/admin/types/delete')) ?>" class="d-inline"
                          onsubmit="return confirm('Удалить тип «<?= $this->e($name) ?>»? Элементы этого типа останутся, но потеряют описание полей.');">
                        <input type="hidden" name="name" value="<?= $this->e($name) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= $this->icon('delete') ?> Удалить</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Типы контента', 'active' => 'types', 'admin_user' => $admin_user ?? null]);
