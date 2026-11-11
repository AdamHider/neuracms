<?php
/**
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
 * @var array $media
 * @var bool $saved
 * @var string $media_error
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$media = $media ?? [];
$saved = $saved ?? false;
$relations = $relations ?? [];
$isNew = $item === null;
$data = $data ?? [];
$fields = $fields ?? [];
$type = $type ?? ($data['type'] ?? 'page');
$kind = $kind ?? ($app->schema->kind($type));
$isPage = $kind === \FlatCms\Content\Schema::KIND_PAGE;
$propFields = array_diff_key($data, array_flip(\FlatCms\Content\Item::RESERVED), $fields);
ob_start();
?>
<?php if ($saved): ?><p class="notice">Сохранено.</p><?php endif; ?>
<?php if (($media_error ?? '') !== ''): ?><p class="error"><?= $this->e($media_error) ?></p><?php endif; ?>
<?php foreach (($errors ?? []) as $error): ?>
    <p class="error"><?= $this->e($error) ?></p>
<?php endforeach; ?>

<?php if (!$isNew && $isPage && $app->module('builder') !== null): ?>
    <div class="panel">
        <p class="muted">Внешний вид страницы задаётся в конструкторе: секции, колонки и частицы.</p>
        <a class="btn btn-primary" href="<?= $this->e($this->url('/admin/builder?id=' . rawurlencode($id))) ?>"><?= $this->icon('builder') ?> Открыть в конструкторе</a>
    </div>
<?php endif; ?>

<form method="post" action="<?= $this->e($this->url($isNew ? '/admin/content/new' : '/admin/content/edit')) ?>">
    <input type="hidden" name="id" value="<?= $this->e($id) ?>">

    <?php if ($isNew): ?>
        <label>Путь (id)
            <input type="text" name="id" value="<?= $this->e($id) ?>" placeholder="<?= $isPage ? 'about' : 'catalog/teapot' ?>" required>
            <small>Папка внутри content/. Например: <?= $isPage ? 'about' : 'catalog/teapot' ?></small>
        </label>
    <?php else: ?>
        <p class="muted">ID: <code><?= $this->e($id) ?></code></p>
    <?php endif; ?>

    <label>Тип
        <select name="type">
            <?php foreach ($types as $value => $label): ?>
                <option value="<?= $this->e($value) ?>" <?= $type === $value ? 'selected' : '' ?>>
                    <?= $this->e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!$isNew): ?><small>Смена типа применяется при сохранении; поля берутся из нового типа.</small><?php endif; ?>
    </label>

    <label>Заголовок <input type="text" name="title" value="<?= $this->e($data['title'] ?? '') ?>" required></label>
    <label>Slug <input type="text" name="slug" value="<?= $this->e($data['slug'] ?? '') ?>"></label>
    <label>Порядок <input type="number" name="order" value="<?= (int) ($data['order'] ?? 0) ?>"></label>
    <label class="checkbox">
        <input type="checkbox" name="published" value="1" <?= ($data['published'] ?? true) ? 'checked' : '' ?>> Опубликован
    </label>

    <?php if ($fields !== []): ?>
        <fieldset>
            <legend>Поля типа «<?= $this->e($types[$type] ?? $type) ?>»</legend>
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
                            <small>Тип-назначение: <?= $this->e($field['to'] ?? '—') ?></small>
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
    <?php endif; ?>

    <fieldset>
        <legend>Свободные свойства</legend>
        <small>Любые дополнительные пары ключ-значение, не описанные в типе.</small>
        <div id="props">
            <?php $index = 0; ?>
            <?php foreach ($propFields as $key => $value): ?>
                <div class="prop-row">
                    <input type="text" name="props[<?= $index ?>][key]" value="<?= $this->e($key) ?>" placeholder="ключ">
                    <input type="text" name="props[<?= $index ?>][value]" value="<?= $this->e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?>" placeholder="значение">
                </div>
                <?php $index++; ?>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < 2; $i++): ?>
                <div class="prop-row">
                    <input type="text" name="props[<?= $index ?>][key]" placeholder="ключ">
                    <input type="text" name="props[<?= $index ?>][value]" placeholder="значение">
                </div>
                <?php $index++; ?>
            <?php endfor; ?>
        </div>
    </fieldset>

    <label><?= $isPage ? 'Содержимое (запасной вариант)' : 'Описание (Markdown)' ?>
        <textarea name="body" rows="<?= $isPage ? 8 : 12 ?>"><?= $this->e($body ?? '') ?></textarea>
        <?php if ($isPage): ?>
            <small>Показывается только если в конструкторе не задан макет. Основной вид страницы задаётся в конструкторе.</small>
        <?php endif; ?>
    </label>

    <button type="submit" class="btn btn-primary"><?= $this->icon('save') ?> Сохранить</button>
</form>

<?php if (!$isNew): ?>
    <section class="media panel">
        <h2><?= $this->icon('media') ?> Медиа</h2>
        <ul class="media__list">
            <?php foreach ($media as $name): ?>
                <li>
                    <a href="<?= $this->e($this->url('/media/' . $id . '/' . $name)) ?>" target="_blank" rel="noopener"><?= $this->e($name) ?></a>
                    <form method="post" action="<?= $this->e($this->url('/admin/media/delete')) ?>">
                        <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                        <input type="hidden" name="name" value="<?= $this->e($name) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= $this->icon('delete') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= $this->e($this->url('/admin/media/upload')) ?>" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $this->e($id) ?>">
            <input type="file" name="file" required>
            <button type="submit" class="btn btn-outline-secondary"><?= $this->icon('media') ?> Загрузить</button>
        </form>
    </section>

    <section class="move panel">
        <h2><?= $this->icon('move') ?> Переместить</h2>
        <form method="post" action="<?= $this->e($this->url('/admin/content/move')) ?>">
            <input type="hidden" name="id" value="<?= $this->e($id) ?>">
            <label>Новый путь <input type="text" name="to" value="<?= $this->e($id) ?>"></label>
            <button type="submit" class="btn btn-outline-secondary"><?= $this->icon('move') ?> Переместить</button>
        </form>
    </section>

    <p><a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/history?id=' . rawurlencode($id))) ?>"><?= $this->icon('history') ?> История версий</a></p>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', [
    'content' => $content,
    'title' => $isNew ? ($isPage ? 'Новая страница' : 'Новый элемент') : 'Редактирование',
    'active' => $isPage ? 'pages' : 'elements',
    'admin_user' => $admin_user ?? null,
]);
