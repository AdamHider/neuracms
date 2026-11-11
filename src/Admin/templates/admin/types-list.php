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
<?php if (($created ?? '') !== ''): ?><p class="notice">Тип «<?= $this->e($created) ?>» сохранён.</p><?php endif; ?>
<?php if (($deleted ?? '') !== ''): ?><p class="notice">Тип «<?= $this->e($deleted) ?>» удалён.</p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?= $this->e($error) ?></p><?php endif; ?>

<p><a class="button" href="<?= $this->e($this->url('/admin/types/new')) ?>">Добавить тип</a></p>

<table class="table">
    <thead>
    <tr>
        <th>Тип</th><th>Название</th><th>Поля</th><th>Связи</th><th>Элементов</th><th>Действия</th>
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
            <td class="actions">
                <a href="<?= $this->e($this->url('/admin/types/edit?name=' . rawurlencode($name))) ?>">Изменить</a>
                <?php if (empty($type['builtin'])): ?>
                    <form method="post" action="<?= $this->e($this->url('/admin/types/delete')) ?>"
                          onsubmit="return confirm('Удалить тип «<?= $this->e($name) ?>»? Элементы этого типа останутся, но потеряют описание полей.');">
                        <input type="hidden" name="name" value="<?= $this->e($name) ?>">
                        <button type="submit" class="danger">Удалить</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Типы контента', 'admin_user' => $admin_user ?? null]);
