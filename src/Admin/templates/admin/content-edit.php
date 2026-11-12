<?php
/**
 * Content editor. One flat list of the type's fields; the item's type is fixed
 * once chosen (new items keep the type they were created with, so a "Товар" opens
 * the product form directly). Action buttons sit at the top, OpenCart/Joomla
 * style, and the page warns before leaving with unsaved changes.
 *
 * @var \FlatCms\Content\Item|null $item
 * @var string $id
 * @var array<string, string> $types
 * @var string $kind
 * @var string $type
 * @var array<string, mixed> $data
 * @var string $body
 * @var array<string, array> $fields
 * @var array<string, list<array{id: string, title: string, type: string}>> $relations
 * @var list<string> $errors
 * @var bool $saved
 * @var string $media_error
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$saved = $saved ?? false;
$relations = $relations ?? [];
$isNew = $item === null;
$data = $data ?? [];
$fields = $fields ?? [];
$type = $type ?? ($data['type'] ?? 'page');
$kind = $kind ?? ($app->schema->kind($type));
$isPage = $kind === \FlatCms\Content\Schema::KIND_PAGE;
$typeLabel = $types[$type] ?? $type;
$builder = !$isNew && $isPage && $app->module('builder') !== null;
$formAction = $this->url($isNew ? '/admin/content/new' : '/admin/content/edit');
ob_start();
?>
<?php if ($saved): ?><p class="notice"><?= $this->icon('check') ?> Сохранено.</p><?php endif; ?>
<?php if (($media_error ?? '') !== ''): ?><p class="error"><?= $this->e($media_error) ?></p><?php endif; ?>
<?php foreach (($errors ?? []) as $error): ?>
    <p class="error"><?= $this->e($error) ?></p>
<?php endforeach; ?>

<form method="post" action="<?= $this->e($formAction) ?>" id="content-form">
    <input type="hidden" name="id" value="<?= $this->e($id) ?>">
    <input type="hidden" name="type" value="<?= $this->e($type) ?>">
    <input type="hidden" name="body" value="<?= $this->e($body ?? '') ?>">

    <div class="editor-bar">
        <button type="submit" class="btn btn-sm btn-primary"><?= $this->icon('save') ?> Сохранить</button>
        <?php if ($builder): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/builder?id=' . rawurlencode($id))) ?>"><?= $this->icon('builder') ?> Конструктор</a>
        <?php endif; ?>
        <?php if (!$isNew): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/history?id=' . rawurlencode($id))) ?>"><?= $this->icon('history') ?> История</a>
            <?php if ($isPage): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener"><?= $this->icon('view') ?> Открыть</a>
            <?php endif; ?>
        <?php endif; ?>
        <span class="editor-bar__spacer"></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url($isPage ? '/admin/pages' : '/admin/elements')) ?>"><?= $this->icon('close') ?> К списку</a>
    </div>

    <fieldset>
        <legend><?= $this->e($isNew ? 'Новый: ' : '') ?><?= $this->e($typeLabel) ?></legend>

        <?php if ($isNew): ?>
            <label>Путь (id)
                <input type="text" name="id" value="<?= $this->e($id) ?>" placeholder="<?= $isPage ? 'about' : 'catalog/teapot' ?>" required>
                <small>Папка внутри content/. Например: <?= $isPage ? 'about' : 'catalog/teapot' ?></small>
            </label>
        <?php else: ?>
            <p class="muted">ID: <code><?= $this->e($id) ?></code> · Тип: <span class="badge text-bg-light"><?= $this->e($typeLabel) ?></span></p>
        <?php endif; ?>

        <label>Заголовок <input type="text" name="title" value="<?= $this->e($data['title'] ?? '') ?>" required></label>
        <label>Slug <input type="text" name="slug" value="<?= $this->e($data['slug'] ?? '') ?>"></label>
        <label>Порядок <input type="number" name="order" value="<?= (int) ($data['order'] ?? 0) ?>"></label>
        <label class="checkbox">
            <input type="checkbox" name="published" value="1" <?= ($data['published'] ?? true) ? 'checked' : '' ?>> Опубликован
        </label>

        <?php foreach ($fields as $name => $field): ?>
            <?php
            $label = (string) ($field['label'] ?? $name);
            $value = $data[$name] ?? ($field['default'] ?? null);
            $inputName = 'fields[' . $name . ']';
            $required = !empty($field['required']);
            ?>
            <label><?= $this->e($label) ?>
                <?php if (($field['type'] ?? '') === 'relation'): ?>
                    <?php $options = $relations[$name] ?? []; ?>
                    <?php if (!empty($field['multiple'])): ?>
                        <select name="<?= $this->e($inputName) ?>[]" multiple size="<?= max(3, min(8, count($options))) ?>">
                            <?php foreach ($options as $option): ?>
                                <option value="<?= $this->e($option['id']) ?>"
                                    <?= in_array($option['id'], is_array($value) ? $value : [], true) ? 'selected' : '' ?>>
                                    <?= $this->e($option['title']) ?> <small>(<?= $this->e($option['id']) ?>)</small>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Можно выбрать несколько. Удерживайте Ctrl (Cmd) для выбора.</small>
                    <?php else: ?>
                        <select name="<?= $this->e($inputName) ?>">
                            <option value="">— не выбрано —</option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= $this->e($option['id']) ?>"
                                    <?= (string) $value === $option['id'] ? 'selected' : '' ?>>
                                    <?= $this->e($option['title']) ?> <small>(<?= $this->e($option['id']) ?>)</small>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                <?php elseif (($field['type'] ?? '') === 'bool'): ?>
                    <span class="checkbox-inline">
                        <input type="checkbox" name="<?= $this->e($inputName) ?>" value="1" <?= $value ? 'checked' : '' ?>>
                    </span>
                <?php elseif (($field['type'] ?? '') === 'select'): ?>
                    <select name="<?= $this->e($inputName) ?>">
                        <option value="">—</option>
                        <?php foreach (($field['options'] ?? []) as $option): ?>
                            <option value="<?= $this->e($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>>
                                <?= $this->e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (($field['type'] ?? '') === 'text' || ($field['type'] ?? '') === 'markdown'): ?>
                    <textarea name="<?= $this->e($inputName) ?>" rows="3"><?= $this->e(is_scalar($value) ? (string) $value : '') ?></textarea>
                <?php elseif (($field['type'] ?? '') === 'list'): ?>
                    <input type="text" name="<?= $this->e($inputName) ?>"
                           value="<?= $this->e(is_array($value) ? implode(', ', $value) : (string) $value) ?>"
                           placeholder="через запятую">
                <?php elseif (($field['type'] ?? '') === 'int' || ($field['type'] ?? '') === 'float'): ?>
                    <input type="number" step="<?= ($field['type'] ?? '') === 'float' ? 'any' : '1' ?>"
                           name="<?= $this->e($inputName) ?>" value="<?= $this->e($value ?? '') ?>">
                <?php else: ?>
                    <input type="text" name="<?= $this->e($inputName) ?>" value="<?= $this->e(is_scalar($value) ? (string) $value : '') ?>">
                <?php endif; ?>
                <?php if ($required): ?><small>Обязательное поле</small><?php endif; ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
</form>

<script>
(function () {
    var form = document.getElementById('content-form');
    if (!form) { return; }
    var initial = new FormData(form);
    var initialSnapshot = JSON.stringify(Array.from(initial.entries()));
    var dirty = false;

    function changed() {
        var current = JSON.stringify(Array.from(new FormData(form).entries()));
        return current !== initialSnapshot;
    }

    form.addEventListener('submit', function () { dirty = false; });
    form.addEventListener('input', function () { dirty = changed(); });
    form.addEventListener('change', function () { dirty = changed(); });

    window.addEventListener('beforeunload', function (event) {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || !dirty) { return; }
        if (link.target === '_blank' || link.hasAttribute('download')) { return; }
        if (!window.confirm('Изменения не сохранены. Покинуть страницу?')) {
            event.preventDefault();
        }
    }, true);
})();
</script>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', [
    'content' => $content,
    'title' => $isNew ? ($isPage ? 'Новая страница' : 'Новый элемент') : 'Редактирование: ' . $item->title(),
    'active' => $isPage ? 'pages' : 'elements',
    'admin_user' => $admin_user ?? null,
]);
