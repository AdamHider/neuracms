<?php
/**
 * Gantry-style page builder: a particle library on the left, a section/column
 * canvas in the middle and a settings panel on the right. builder.js turns the
 * JSON payload into a drag-and-drop editor; without JS the JSON textarea still
 * lets you edit the layout by hand.
 *
 * @var \FlatCms\Content\Item|null $item
 * @var string $id
 * @var array<string, mixed> $layout     Page layout with globals already expanded
 * @var array<string, array<string, array>>|null $globals
 * @var array<string, \FlatCms\Modules\Builder\BlockType> $types
 * @var array<string, list<array>> $categories
 * @var list<array{name: string, title: string, widths: list<int>}> $presets
 * @var array<string, array{label: string, fields: list<string>}> $elements
 * @var array<string, array{label: string, kind: string, properties: list<array>}> $menu
 * @var list<string> $media
 * @var int $columns
 * @var bool $saved
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
$payload = [
    'layout' => is_array($layout) ? $layout : $layout->toArray(),
    'types' => array_values(array_map(static fn ($t): array => $t->toArray(), $types)),
    'categories' => $categories,
    'presets' => $presets,
    'elements' => $elements ?? [],
    'menu' => $menu ?? [],
    'media' => array_values($media ?? []),
    'globals' => $globals ?? [],
    'uploadUrl' => '/admin/builder/media',
    'pageId' => $id,
    'mediaId' => $id,
    'columns' => $columns,
    'icons' => \FlatCms\Admin\Icons::all(),
];
$title = 'Конструктор: ' . $item?->title();
ob_start();
?>
<?php if (!empty($saved)): ?><p class="notice"><?= $this->icon('check') ?> Сохранено.</p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p><?php endif; ?>

<div class="builder-bar">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-builder-back="1"><?= $this->icon('up') ?> Назад</button>
    <span class="muted">
        <?= $this->icon('builder') ?>
        Страница <code><?= $this->e($id) ?></code>
    </span>
    <span class="builder-bar__spacer"></span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener"><?= $this->icon('view') ?> Открыть</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($id))) ?>"><?= $this->icon('edit') ?> Обычное редактирование</a>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-builder-toggle="json"><?= $this->icon('html') ?> JSON</button>
    <button type="submit" form="builder-form" class="btn btn-sm btn-primary"><?= $this->icon('save') ?> Сохранить</button>
</div>

<form method="post" action="<?= $this->e($this->url('/admin/builder/save')) ?>" id="builder-form">
    <input type="hidden" name="id" value="<?= $this->e($id) ?>">

    <div class="builder" id="builder"
         data-builder='<?= $this->e((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>

        <aside class="builder__library">
            <h2><?= $this->icon('grid') ?> Элементы</h2>
            <input type="search" class="builder__search" id="builder-search" placeholder="Поиск…" aria-label="Поиск элементов">
            <div id="builder-library"></div>

            <div class="builder__globals">
                <h2><?= $this->icon('global') ?> Глобальные</h2>
                <p class="muted">Один раз создали — используете на любой странице. Перетащите на страницу, правьте прямо на ней.</p>
                <div id="builder-globals"></div>
            </div>
        </aside>

        <div class="builder__canvas-wrap">
            <div class="builder__toolbar">
                <span class="muted" id="builder-count"></span>
                <span class="builder-bar__spacer"></span>
                <div class="btn-group btn-group-sm" role="group" aria-label="История изменений">
                    <button type="button" class="btn btn-outline-secondary" data-builder-undo="1"
                            title="Отменить (Ctrl+Z)" aria-label="Отменить" disabled><?= $this->icon('undo') ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-builder-redo="1"
                            title="Повторить (Ctrl+Shift+Z)" aria-label="Повторить" disabled><?= $this->icon('redo') ?></button>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $this->icon('add') ?> Добавить секцию
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" id="builder-presets">
                        <?php foreach ($presets as $preset): ?>
                            <li>
                                <button class="dropdown-item" type="button" data-preset='<?= $this->e((string) json_encode($preset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
                                    <?php if (!empty($preset['icon'])): ?><?= $this->icon($preset['icon']) ?><?php else: ?><span class="preset-preview"><?php foreach ($preset['widths'] as $w): ?><span style="flex:<?= (int) $w ?>"></span><?php endforeach; ?></span><?php endif; ?>
                                    <?= $this->e($preset['title']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                        <?php
                        $sectionGlobals = $globals[\FlatCms\Modules\Builder\GlobalLibrary::SECTION] ?? [];
                        ?>
                        <?php if ($sectionGlobals !== []): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header"><?= $this->icon('global') ?> Глобальные секции</h6></li>
                            <?php foreach ($sectionGlobals as $name => $entry): ?>
                                <li>
                                    <button class="dropdown-item" type="button" data-global-section-add="<?= $this->e((string) $name) ?>">
                                        <?= $this->icon($entry['role'] !== '' ? 'section' : 'global') ?>
                                        <?= $this->e($entry['title']) ?>
                                        <?php if ($entry['role'] !== ''): ?>
                                            <span class="builder__badge"><?= $this->e($entry['role'] === 'header' ? 'Шапка' : 'Подвал') ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="builder__canvas" id="builder-canvas"></div>
        </div>

        <aside class="builder__settings" id="builder-settings">
            <div class="builder__settings-head" id="builder-settings-head"></div>
            <div class="builder__settings-tabs" id="builder-settings-tabs" role="tablist"></div>
            <div class="builder__settings-body" id="builder-settings-body">
                <p class="muted">Выберите секцию, ряд, колонку или элемент, чтобы настроить его.</p>
            </div>
        </aside>
    </div>

    <div class="builder__source" id="builder-json-wrap" hidden>
        <label>
            <span class="muted">JSON макета (синхронизируется со визуальным редактором)</span>
            <textarea name="layout_json" id="builder-json" rows="20" spellcheck="false"></textarea>
        </label>
    </div>
</form>

<script src="<?= $this->e($this->ownerAsset('builder', 'js/builder.js')) ?>"></script>
<?php
$content = ob_get_clean();
$head = '<link rel="stylesheet" href="' . $this->e($this->ownerAsset('builder', 'css/builder.css')) . '">';
echo $this->partial('admin/layout', [
    'content' => $content,
    'title' => $title,
    'active' => 'pages',
    'head' => $head,
    'admin_user' => $admin_user ?? null,
]);
