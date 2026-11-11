<?php
/**
 * Gantry-style page builder: a particle library on the left, a section/column
 * canvas in the middle and a settings panel on the right. builder.js turns the
 * JSON payload into a drag-and-drop editor; without JS the JSON textarea still
 * lets you edit the layout by hand.
 *
 * @var \FlatCms\Content\Item|null $item
 * @var string $id
 * @var string $region
 * @var string $region_label
 * @var \FlatCms\Modules\Builder\Layout $layout
 * @var array<string, \FlatCms\Modules\Builder\BlockType> $types
 * @var array<string, list<array>> $categories
 * @var list<array{name: string, title: string, widths: list<int>}> $presets
 * @var array<string, array{label: string, fields: list<string>}> $elements
 * @var list<string> $media
 * @var int $columns
 * @var bool $saved
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
$region = $region ?? '';
$isRegion = $region !== '';
$mediaId = $isRegion ? '_regions/' . $region : $id;
$payload = [
    'layout' => $layout->toArray(),
    'types' => array_values(array_map(static fn ($t): array => $t->toArray(), $types)),
    'categories' => $categories,
    'presets' => $presets,
    'elements' => $elements ?? [],
    'media' => array_values($media ?? []),
    'uploadUrl' => '/admin/builder/media',
    'pageId' => $id,
    'mediaId' => $mediaId,
    'region' => $region,
    'columns' => $columns,
    'icons' => \FlatCms\Admin\Icons::all(),
];
$title = $isRegion ? 'Область: ' . ($region_label ?? $region) : 'Конструктор: ' . $item?->title();
ob_start();
?>
<?php if (!empty($saved)): ?><p class="notice"><?= $this->icon('check') ?> Сохранено.</p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p><?php endif; ?>

<div class="builder-bar">
    <span class="muted">
        <?php if ($isRegion): ?>
            <?= $this->icon('regions') ?> Область сайта <code><?= $this->e($region) ?></code>
        <?php else: ?>
            <?= $this->icon('builder') ?> Страница <code><?= $this->e($id) ?></code>
        <?php endif; ?>
    </span>
    <span class="builder-bar__spacer"></span>
    <?php if ($isRegion): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/')) ?>" target="_blank" rel="noopener"><?= $this->icon('view') ?> Открыть сайт</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/regions')) ?>"><?= $this->icon('regions') ?> Все области</a>
    <?php else: ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener"><?= $this->icon('view') ?> Открыть</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($id))) ?>"><?= $this->icon('edit') ?> Обычное редактирование</a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-builder-toggle="json"><?= $this->icon('html') ?> JSON</button>
    <button type="submit" form="builder-form" class="btn btn-sm btn-primary"><?= $this->icon('save') ?> Сохранить</button>
</div>

<form method="post" action="<?= $this->e($this->url('/admin/builder/save')) ?>" id="builder-form">
    <?php if ($isRegion): ?>
        <input type="hidden" name="region" value="<?= $this->e($region) ?>">
    <?php else: ?>
        <input type="hidden" name="id" value="<?= $this->e($id) ?>">
    <?php endif; ?>

    <div class="builder" id="builder"
         data-builder='<?= $this->e((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>

        <aside class="builder__library">
            <h2><?= $this->icon('grid') ?> Элементы</h2>
            <input type="search" class="builder__search" id="builder-search" placeholder="Поиск…" aria-label="Поиск элементов">
            <div id="builder-library"></div>
        </aside>

        <div class="builder__canvas-wrap">
            <div class="builder__toolbar">
                <span class="muted" id="builder-count"></span>
                <span class="builder-bar__spacer"></span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?= $this->icon('add') ?> Добавить секцию
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" id="builder-presets">
                        <?php foreach ($presets as $preset): ?>
                            <li>
                                <button class="dropdown-item" type="button" data-preset='<?= $this->e((string) json_encode($preset['widths'])) ?>'>
                                    <span class="preset-preview"><?php foreach ($preset['widths'] as $w): ?><span style="flex:<?= (int) $w ?>"></span><?php endforeach; ?></span>
                                    <?= $this->e($preset['title']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="builder__canvas" id="builder-canvas"></div>
        </div>

        <aside class="builder__settings" id="builder-settings">
            <h2><?= $this->icon('settings') ?> Настройки</h2>
            <div id="builder-settings-body">
                <p class="muted">Выберите секцию или элемент, чтобы настроить его.</p>
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
    'active' => $isRegion ? 'regions' : 'pages',
    'head' => $head,
    'admin_user' => $admin_user ?? null,
]);
