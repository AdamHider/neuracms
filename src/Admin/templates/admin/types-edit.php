<?php
/**
 * Type editor. Same two-column layout as the content editor: the main column
 * holds the type's identity and its fields, the side column holds the admin-menu
 * settings and a summary. The fields table stays a table because it is a matrix
 * of column definitions.
 *
 * @var string $name
 * @var string $label
 * @var array<string, array> $fields
 * @var list<string> $indexed
 * @var bool $builtin
 * @var string $kind
 * @var array{menu: bool, label: string, icon: string, order: int} $admin
 * @var bool $isNew
 * @var array<string, string> $typeNames
 * @var list<string> $errors
 * @var list<string> $referenced_by
 * @var \FlatCms\Http\TemplateContext $this
 */
$admin = $admin ?? ['menu' => false, 'label' => '', 'icon' => '', 'order' => 100];
$fieldTypes = [
    'string' => 'Строка',
    'text' => 'Текст',
    'int' => 'Целое',
    'float' => 'Дробное',
    'bool' => 'Флажок',
    'list' => 'Список',
    'file' => 'Файл',
    'select' => 'Выбор',
    'date' => 'Дата',
    'relation' => 'Связь с типом',
];
$rows = [];
foreach ($fields as $fieldName => $field) {
    $rows[] = [
        'name' => $fieldName,
        'type' => (string) ($field['type'] ?? 'string'),
        'label' => (string) ($field['label'] ?? ''),
        'required' => !empty($field['required']),
        'indexed' => in_array($fieldName, $indexed, true),
        'default' => $field['default'] ?? '',
        'to' => (string) ($field['to'] ?? ''),
        'multiple' => !empty($field['multiple']),
        'options' => isset($field['options']) && is_array($field['options']) ? implode(', ', $field['options']) : '',
    ];
}
ob_start();
?>
<?php foreach (($errors ?? []) as $error): ?>
    <p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p>
<?php endforeach; ?>

<form method="post" action="<?= $this->e($this->url($isNew ? '/admin/types/new' : '/admin/types/edit')) ?>" id="type-form">
    <input type="hidden" name="name" value="<?= $this->e($name) ?>">

    <div class="editor-bar">
        <button type="submit" class="btn btn-sm btn-primary"><?= $this->icon('save') ?> Сохранить тип</button>
        <span class="editor-bar__spacer"></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/types')) ?>"><?= $this->icon('close') ?> К списку типов</a>
    </div>

    <div class="editor-grid">
        <div class="editor-grid__main">
            <section class="panel">
                <h2><?= $this->icon('types') ?> <?= $this->e($isNew ? 'Новый тип' : 'Тип «' . $label . '»') ?></h2>
                <div class="form-grid">
                    <?php if ($isNew): ?>
                        <label>Имя типа (латиница)
                            <input type="text" name="name" value="<?= $this->e($name) ?>" placeholder="product" pattern="[a-z][a-z0-9_-]*" required>
                            <small>Имя файла и значение поля <code>type</code>. Например: product, news, service.</small>
                        </label>
                    <?php endif; ?>
                    <label>Название
                        <input type="text" name="label" value="<?= $this->e($label) ?>" placeholder="Товар">
                    </label>
                    <label class="form-field--full">Назначение
                        <select name="kind">
                            <option value="page" <?= ($kind ?? 'element') === 'page' ? 'selected' : '' ?>>Страница — маршрутизируемая, вид задаётся конструктором</option>
                            <option value="element" <?= ($kind ?? 'element') === 'element' ? 'selected' : '' ?>>Элемент — данные, выводятся частицами (карточки, списки)</option>
                        </select>
                        <small>Страницы имеют собственный URL и открываются в конструкторе. Элементы — это структурированные данные без страницы.</small>
                    </label>
                </div>
            </section>

            <section class="panel">
                <h2><?= $this->icon('list') ?> Поля</h2>
                <div class="table-responsive">
                    <table class="table" id="fields-table">
                        <thead>
                        <tr>
                            <th>Имя</th><th>Тип</th><th>Подпись</th><th>Обяз.</th><th>В индексе</th>
                            <th>Назначение / варианты / значение по умолчанию</th><th></th>
                        </tr>
                        </thead>
                        <tbody id="fields-body">
                        <?php foreach ($rows as $i => $row): ?>
                            <tr class="field-row">
                                <td><input type="text" name="fields[<?= $i ?>][name]" value="<?= $this->e($row['name']) ?>" placeholder="price"></td>
                                <td>
                                    <select name="fields[<?= $i ?>][type]" class="field-type">
                                        <?php foreach ($fieldTypes as $value => $text): ?>
                                            <option value="<?= $this->e($value) ?>" <?= $row['type'] === $value ? 'selected' : '' ?>><?= $this->e($text) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="fields[<?= $i ?>][label]" value="<?= $this->e($row['label']) ?>" placeholder="Цена"></td>
                                <td><input type="checkbox" name="fields[<?= $i ?>][required]" value="1" <?= $row['required'] ? 'checked' : '' ?>></td>
                                <td><input type="checkbox" name="fields[<?= $i ?>][indexed]" value="1" <?= $row['indexed'] ? 'checked' : '' ?>></td>
                                <td>
                                    <input type="text" name="fields[<?= $i ?>][to]" value="<?= $this->e($row['to']) ?>" class="field-to" list="type-list" placeholder="тип-назначение">
                                    <input type="text" name="fields[<?= $i ?>][options]" value="<?= $this->e($row['options']) ?>" class="field-options" placeholder="варианты через запятую">
                                    <label class="checkbox-inline"><input type="checkbox" name="fields[<?= $i ?>][multiple]" value="1" class="field-multiple" <?= $row['multiple'] ? 'checked' : '' ?>> много</label>
                                    <input type="text" name="fields[<?= $i ?>][default]" value="<?= $this->e(is_scalar($row['default']) ? (string) $row['default'] : '') ?>" class="field-default" placeholder="по умолчанию">
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Удалить поле"><?= $this->icon('delete') ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" id="add-row" class="btn btn-outline-secondary btn-sm"><?= $this->icon('add') ?> Добавить поле</button>
                <datalist id="type-list">
                    <?php foreach ($typeNames as $typeName => $typeLabel): ?>
                        <option value="<?= $this->e($typeName) ?>"><?= $this->e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <small class="d-block mt-2">Для поля типа «Связь с типом» укажите тип-назначение (например <code>category</code>) и, при необходимости, отметьте «много».</small>
            </section>
        </div>

        <aside class="editor-grid__side">
            <section class="panel">
                <h2><?= $this->icon('menu') ?> Пункт в меню админки</h2>
                <label class="checkbox-inline">
                    <input type="checkbox" name="admin_menu" value="1" <?= !empty($admin['menu']) ? 'checked' : '' ?>>
                    Отдельный пункт в боковом меню
                </label>
                <small>Например, тип «Статьи» получит свой пункт рядом со «Страницами» и «Элементами».</small>
                <label>Название пункта
                    <input type="text" name="admin_label" value="<?= $this->e($admin['label']) ?>" placeholder="<?= $this->e($label !== '' ? $label : 'Статьи') ?>">
                </label>
                <label>Иконка (Bootstrap Icons)
                    <input type="text" name="admin_icon" value="<?= $this->e($admin['icon']) ?>" placeholder="content" list="icon-list">
                </label>
                <label>Порядок
                    <input type="number" name="admin_order" value="<?= (int) ($admin['order'] ?? 100) ?>" step="1">
                </label>
                <datalist id="icon-list">
                    <?php foreach (\FlatCms\Admin\Icons::all() as $key => $glyph): ?>
                        <option value="<?= $this->e($key) ?>"><?= $this->e($key) ?> — bi-<?= $this->e($glyph) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <small>Иконка задаётся смысловым именем из набора админки (<code>content</code>, <code>docs</code>, <code>list</code>) или любым именем Bootstrap Icons.</small>
            </section>

            <section class="panel">
                <h2><?= $this->icon('info') ?> Информация</h2>
                <dl class="meta-list">
                    <dt>Имя</dt><dd><code><?= $this->e($name !== '' ? $name : '—') ?></code></dd>
                    <dt>Тип</dt><dd><?= $builtin ? 'встроенный' : 'пользовательский' ?></dd>
                    <dt>Полей</dt><dd><?= count($fields) ?></dd>
                </dl>
                <?php if (!empty($referenced_by)): ?>
                    <p class="muted mt-2 mb-0">На этот тип ссылаются: <?= $this->e(implode(', ', $referenced_by)) ?>. Переименовать нельзя — создайте новый и перенесите элементы.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</form>

<template id="field-row-template">
    <tr class="field-row">
        <td><input type="text" data-name="name" placeholder="price"></td>
        <td>
            <select data-name="type" class="field-type">
                <?php foreach ($fieldTypes as $value => $text): ?>
                    <option value="<?= $this->e($value) ?>"><?= $this->e($text) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" data-name="label" placeholder="Цена"></td>
        <td><input type="checkbox" data-name="required" value="1"></td>
        <td><input type="checkbox" data-name="indexed" value="1"></td>
        <td>
            <input type="text" data-name="to" class="field-to" list="type-list" placeholder="тип-назначение">
            <input type="text" data-name="options" class="field-options" placeholder="варианты через запятую">
            <label class="checkbox-inline"><input type="checkbox" data-name="multiple" value="1" class="field-multiple"> много</label>
            <input type="text" data-name="default" class="field-default" placeholder="по умолчанию">
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Удалить поле"><?= $this->icon('delete') ?></button></td>
    </tr>
</template>

<script>
(function () {
    var body = document.getElementById('fields-body');
    var template = document.getElementById('field-row-template');

    function reindex() {
        body.querySelectorAll('.field-row').forEach(function (row, i) {
            row.querySelectorAll('[data-name], [name]').forEach(function (el) {
                var key = el.getAttribute('data-name') || (el.getAttribute('name') || '').replace(/^fields\[\d+\]\[(\w+)\]$/, '$1');
                if (key) {
                    el.setAttribute('name', 'fields[' + i + '][' + key + ']');
                }
            });
            toggleRow(row);
        });
    }

    function toggleRow(row) {
        var type = row.querySelector('.field-type').value;
        row.querySelector('.field-to').style.display = type === 'relation' ? '' : 'none';
        row.querySelector('.field-multiple').parentNode.style.display = type === 'relation' ? '' : 'none';
        row.querySelector('.field-options').style.display = type === 'select' ? '' : 'none';
        row.querySelector('.field-default').style.display = (type === 'relation') ? 'none' : '';
    }

    document.getElementById('add-row').addEventListener('click', function () {
        body.appendChild(template.content.cloneNode(true));
        reindex();
    });

    body.addEventListener('click', function (event) {
        if (event.target.closest('.remove-row')) {
            event.target.closest('.field-row').remove();
            reindex();
        }
    });

    body.addEventListener('change', function (event) {
        if (event.target.classList.contains('field-type')) {
            toggleRow(event.target.closest('.field-row'));
        }
    });

    reindex();
})();
</script>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => $isNew ? 'Новый тип' : 'Тип: ' . $label, 'active' => 'types', 'admin_user' => $admin_user ?? null]);
