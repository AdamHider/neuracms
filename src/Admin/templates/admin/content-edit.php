<?php
/**
 * Content editor for a page or element. One flat list of the type's fields in a
 * two-column layout: the form on the left, publication and location on the right.
 * A page's appearance comes from the builder, so there is no markdown body here.
 * The page warns before leaving with unsaved changes.
 *
 * @var \FlatCms\Content\Item|null $item
 * @var string $id
 * @var array<string, string> $types
 * @var string $kind
 * @var string $type
 * @var array<string, mixed> $data
 * @var array<string, array> $fields
 * @var array<string, list<array{id: string, title: string, type: string}>> $relations
 * @var list<string> $errors
 * @var bool $saved
 * @var list<string> $media
 * @var string $media_error
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$saved = $saved ?? false;
$relations = $relations ?? [];
$media = $media ?? [];
$isNew = $item === null;
$data = $data ?? [];
$fields = $fields ?? [];
$type = $type ?? ($data['type'] ?? 'page');
$kind = $kind ?? ($app->schema->kind($type));
$isPage = $kind === \FlatCms\Content\Schema::KIND_PAGE;
$typeLabel = $types[$type] ?? $type;
$builder = !$isNew && $isPage && $app->module('builder') !== null;
$formAction = $this->url($isNew ? '/admin/content/new' : '/admin/content/edit');

// Reserved fields are rendered explicitly, so they must not repeat in the loop.
$common = ['title', 'slug', 'order', 'published'];
$typeFields = array_diff_key($fields, array_flip($common));
$listHref = $this->url($isPage ? '/admin/pages' : '/admin/elements');
$wideTypes = ['relation', 'text'];

/**
 * Render one input for a schema field.
 *
 * @param array<string, mixed> $field
 */
$renderField = function (string $name, array $field) use ($data, $relations): string {
    $value = $data[$name] ?? ($field['default'] ?? null);
    $inputName = 'fields[' . $name . ']';
    $ftype = (string) ($field['type'] ?? 'string');
    $html = '<label>' . $this->e((string) ($field['label'] ?? $name));

    if ($ftype === 'relation') {
        $options = $relations[$name] ?? [];
        if (!empty($field['multiple'])) {
            $selected = is_array($value) ? $value : [];
            $html .= '<select name="' . $this->e($inputName) . '[]" multiple size="' . max(3, min(8, count($options))) . '">';
            foreach ($options as $option) {
                $html .= '<option value="' . $this->e($option['id']) . '"'
                    . (in_array($option['id'], $selected, true) ? ' selected' : '') . '>'
                    . $this->e($option['title']) . ' (' . $this->e($option['id']) . ')</option>';
            }
            $html .= '</select><small>Удерживайте Ctrl (Cmd), чтобы выбрать несколько.</small>';
        } else {
            $html .= '<select name="' . $this->e($inputName) . '"><option value="">— не выбрано —</option>';
            foreach ($options as $option) {
                $html .= '<option value="' . $this->e($option['id']) . '"'
                    . ((string) $value === $option['id'] ? ' selected' : '') . '>'
                    . $this->e($option['title']) . ' (' . $this->e($option['id']) . ')</option>';
            }
            $html .= '</select>';
        }
    } elseif ($ftype === 'bool') {
        $html .= '<span class="checkbox-inline"><input type="checkbox" name="' . $this->e($inputName) . '" value="1"'
            . ($value ? ' checked' : '') . '></span>';
    } elseif ($ftype === 'select') {
        $html .= '<select name="' . $this->e($inputName) . '"><option value="">—</option>';
        foreach (($field['options'] ?? []) as $option) {
            $html .= '<option value="' . $this->e($option) . '"'
                . ((string) $value === (string) $option ? ' selected' : '') . '>' . $this->e($option) . '</option>';
        }
        $html .= '</select>';
    } elseif ($ftype === 'text') {
        $html .= '<textarea name="' . $this->e($inputName) . '" rows="3">'
            . $this->e(is_scalar($value) ? (string) $value : '') . '</textarea>';
    } elseif ($ftype === 'list') {
        $html .= '<input type="text" name="' . $this->e($inputName) . '" value="'
            . $this->e(is_array($value) ? implode(', ', $value) : (string) $value) . '" placeholder="через запятую">';
    } elseif ($ftype === 'int' || $ftype === 'float') {
        $html .= '<input type="number" step="' . ($ftype === 'float' ? 'any' : '1') . '" name="'
            . $this->e($inputName) . '" value="' . $this->e($value ?? '') . '">';
    } elseif ($ftype === 'date') {
        $html .= '<input type="date" name="' . $this->e($inputName) . '" value="'
            . $this->e(is_scalar($value) ? (string) $value : '') . '">';
    } else {
        $html .= '<input type="text" name="' . $this->e($inputName) . '" value="'
            . $this->e(is_scalar($value) ? (string) $value : '') . '">';
    }

    if (!empty($field['required'])) {
        $html .= '<small>Обязательное поле</small>';
    }

    return $html . '</label>';
};
ob_start();
?>
<?php if ($saved): ?><p class="notice"><?= $this->icon('check') ?> Сохранено.</p><?php endif; ?>
<?php if (($media_error ?? '') !== ''): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($media_error) ?></p><?php endif; ?>
<?php foreach (($errors ?? []) as $error): ?>
    <p class="error"><?= $this->e($error) ?></p>
<?php endforeach; ?>

<form method="post" action="<?= $this->e($formAction) ?>" id="content-form">
    <input type="hidden" name="type" value="<?= $this->e($type) ?>">

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
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($listHref) ?>"><?= $this->icon('close') ?> К списку</a>
    </div>

    <div class="editor-grid">
        <div class="editor-grid__main">
            <section class="panel">
                <h2><?= $this->icon($isPage ? 'content' : 'elements') ?> <?= $this->e($isNew ? 'Новый: ' : '') ?><?= $this->e($typeLabel) ?></h2>
                <div class="form-grid">
                    <?php if ($isNew): ?>
                        <label class="form-field--full">Путь (id)
                            <input type="text" name="id" value="<?= $this->e($id) ?>" placeholder="<?= $isPage ? 'about' : 'catalog/teapot' ?>" required>
                            <small>Папка внутри content/. Например: <?= $isPage ? 'about' : 'catalog/teapot' ?></small>
                        </label>
                    <?php endif; ?>
                    <label class="form-field--full">Заголовок
                        <input type="text" name="title" value="<?= $this->e($data['title'] ?? '') ?>" required autofocus>
                    </label>
                    <label>Slug
                        <input type="text" name="slug" value="<?= $this->e($data['slug'] ?? '') ?>">
                    </label>
                    <label>Порядок
                        <input type="number" name="order" value="<?= (int) ($data['order'] ?? 0) ?>">
                    </label>
                </div>
            </section>

            <?php if ($typeFields !== []): ?>
                <section class="panel">
                    <h2><?= $this->icon('list') ?> Поля типа</h2>
                    <div class="form-grid">
                        <?php foreach ($typeFields as $name => $field): ?>
                            <div class="<?= in_array((string) ($field['type'] ?? ''), $wideTypes, true) ? 'form-field--full' : '' ?>">
                                <?= $renderField((string) $name, $field) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="editor-grid__side">
            <section class="panel">
                <h2><?= $this->icon('check') ?> Публикация</h2>
                <label class="checkbox">
                    <input type="checkbox" name="published" value="1" <?= ($data['published'] ?? true) ? 'checked' : '' ?>> Опубликован
                </label>
                <p class="muted mb-0">Черновики видны только в админке.</p>
            </section>

            <section class="panel">
                <h2><?= $this->icon('info') ?> Информация</h2>
                <dl class="meta-list">
                    <dt>ID</dt><dd><code><?= $this->e($id !== '' ? $id : '—') ?></code></dd>
                    <dt>Тип</dt><dd><span class="badge text-bg-light"><?= $this->e($typeLabel) ?></span></dd>
                    <dt>Назначение</dt><dd><?= $isPage ? 'Страница' : 'Элемент' ?></dd>
                </dl>
            </section>
        </aside>
    </div>
</form>

<?php if (!$isNew): ?>
    <div class="editor-grid editor-grid--extra">
        <div class="editor-grid__main">
            <section class="panel">
                <h2><?= $this->icon('media') ?> Медиа</h2>
                <?php if ($media === []): ?>
                    <p class="muted">Файлов пока нет.</p>
                <?php else: ?>
                    <ul class="media__list">
                        <?php foreach ($media as $name): ?>
                            <li>
                                <a href="<?= $this->e($this->url('/media/' . $id . '/' . $name)) ?>" target="_blank" rel="noopener"><?= $this->e($name) ?></a>
                                <form method="post" action="<?= $this->e($this->url('/admin/media/delete')) ?>" class="ms-auto">
                                    <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                                    <input type="hidden" name="name" value="<?= $this->e($name) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить"><?= $this->icon('delete') ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="<?= $this->e($this->url('/admin/media/upload')) ?>" enctype="multipart/form-data" class="d-flex gap-2 align-items-center mt-2">
                    <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                    <input type="file" name="file" required>
                    <button type="submit" class="btn btn-outline-secondary"><?= $this->icon('media') ?> Загрузить</button>
                </form>
            </section>
        </div>
        <aside class="editor-grid__side">
            <section class="panel">
                <h2><?= $this->icon('move') ?> Переместить</h2>
                <form method="post" action="<?= $this->e($this->url('/admin/content/move')) ?>">
                    <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                    <label>Новый путь
                        <input type="text" name="to" value="<?= $this->e($id) ?>">
                    </label>
                    <button type="submit" class="btn btn-outline-secondary"><?= $this->icon('move') ?> Переместить</button>
                </form>
            </section>
        </aside>
    </div>
<?php endif; ?>

<script>
(function () {
    var form = document.getElementById('content-form');
    if (!form) { return; }
    var initialSnapshot = JSON.stringify(Array.from(new FormData(form).entries()));
    var dirty = false;

    function changed() {
        return JSON.stringify(Array.from(new FormData(form).entries())) !== initialSnapshot;
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
    'title' => $isNew ? ($isPage ? 'Новая страница' : 'Новый элемент') : $item->title(),
    'active' => $isPage ? 'pages' : 'elements',
    'admin_user' => $admin_user ?? null,
]);
