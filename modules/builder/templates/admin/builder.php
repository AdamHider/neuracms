<?php
/**
 * Block editor. Blocks are edited as JSON in a textarea plus a visual preview
 * driven by builder.js; the types list powers the "add block" menu.
 *
 * @var \FlatCms\Content\Item $item
 * @var string $id
 * @var \FlatCms\Modules\Builder\Layout $layout
 * @var array<string, \FlatCms\Modules\Builder\BlockType> $types
 * @var bool $saved
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
$json = json_encode($layout->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
ob_start();
?>
<?php if (!empty($saved)): ?><p class="notice">Сохранено.</p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?= $this->e($error) ?></p><?php endif; ?>

<p class="muted">
    Элемент: <code><?= $this->e($id) ?></code> ·
    <a href="<?= $this->e($this->url($item->url())) ?>" target="_blank" rel="noopener">открыть страницу</a> ·
    <a href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($id))) ?>">обычное редактирование</a>
</p>

<form method="post" action="<?= $this->e($this->url('/admin/builder/save')) ?>" id="builder-form">
    <input type="hidden" name="id" value="<?= $this->e($id) ?>">

    <div class="builder" data-types='<?= $this->e((string) json_encode(array_map(
        static fn ($type): array => $type->toArray(),
        $types
    ), JSON_UNESCAPED_UNICODE)) ?>'>
        <div class="builder__list">
            <h2>Блоки</h2>
            <div id="builder-blocks"></div>
            <div class="builder__add">
                <select id="builder-add-type">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= $this->e($type->name()) ?>"><?= $this->e($type->title()) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="builder-add">Добавить блок</button>
            </div>
        </div>

        <div class="builder__source">
            <h2>JSON</h2>
            <label>
                <textarea name="layout_json" id="builder-json" rows="24" spellcheck="false"><?= $this->e((string) $json) ?></textarea>
            </label>
            <p class="muted">Правки в списке и в JSON синхронизируются. Неизвестные типы блоков отбрасываются при сохранении.</p>
        </div>
    </div>

    <button type="submit">Сохранить</button>
</form>

<script src="<?= $this->e($this->url('/assets/builder/js/builder.js')) ?>"></script>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Конструктор: ' . $item->title(), 'admin_user' => $admin_user ?? null]);
