<?php
/**
 * @var \FlatCms\Content\Item|null $item
 * @var string $id
 * @var array $types
 * @var array $errors
 * @var array $media
 * @var bool $saved
 * @var string $media_error
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$media = $media ?? [];
$saved = $saved ?? false;
$isNew = $item === null;
$data = $item?->all() ?? [];
$props = $item?->props() ?? [];
$mediaError = (string) ($media_error ?? '');
ob_start();
?>
<?php if ($saved): ?><p class="notice">Сохранено.</p><?php endif; ?>
<?php if ($mediaError !== ''): ?><p class="error"><?= $this->e($mediaError) ?></p><?php endif; ?>
<?php foreach (($errors ?? []) as $error): ?>
    <p class="error"><?= $this->e($error) ?></p>
<?php endforeach; ?>

<form method="post" action="<?= $this->e($this->url($isNew ? '/admin/content/new' : '/admin/content/edit')) ?>">
    <input type="hidden" name="id" value="<?= $this->e($id) ?>">

    <?php if ($isNew): ?>
        <label>Путь (id)
            <input type="text" name="id" value="<?= $this->e($id) ?>" placeholder="blog/second-post" required>
            <small>Папка внутри content/. Например: blog/second-post</small>
        </label>
    <?php else: ?>
        <p class="muted">ID: <code><?= $this->e($id) ?></code></p>
    <?php endif; ?>

    <label>Тип
        <select name="type">
            <?php foreach ($types as $type): ?>
                <option value="<?= $this->e($type) ?>" <?= ($data['type'] ?? 'page') === $type ? 'selected' : '' ?>>
                    <?= $this->e($type) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Заголовок <input type="text" name="title" value="<?= $this->e($data['title'] ?? '') ?>" required></label>
    <label>Slug <input type="text" name="slug" value="<?= $this->e($data['slug'] ?? '') ?>"></label>
    <label>Порядок <input type="number" name="order" value="<?= (int) ($data['order'] ?? 0) ?>"></label>
    <label class="checkbox">
        <input type="checkbox" name="published" value="1" <?= ($data['published'] ?? true) ? 'checked' : '' ?>> Опубликован
    </label>

    <fieldset>
        <legend>Дополнительные свойства</legend>
        <div id="props">
            <?php $index = 0; ?>
            <?php foreach ($props as $key => $value): ?>
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

    <label>Текст (Markdown)
        <textarea name="body" rows="16"><?= $this->e($item?->body() ?? '') ?></textarea>
    </label>

    <button type="submit">Сохранить</button>
</form>

<?php if (!$isNew): ?>
    <section class="media">
        <h2>Медиа</h2>
        <ul class="media__list">
            <?php foreach ($media as $name): ?>
                <li>
                    <a href="<?= $this->e($this->url('/media/' . $id . '/' . $name)) ?>" target="_blank" rel="noopener"><?= $this->e($name) ?></a>
                    <form method="post" action="<?= $this->e($this->url('/admin/media/delete')) ?>">
                        <input type="hidden" name="id" value="<?= $this->e($id) ?>">
                        <input type="hidden" name="name" value="<?= $this->e($name) ?>">
                        <button type="submit" class="danger">Удалить</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= $this->e($this->url('/admin/media/upload')) ?>" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $this->e($id) ?>">
            <input type="file" name="file" required>
            <button type="submit">Загрузить</button>
        </form>
    </section>

    <section class="move">
        <h2>Переместить</h2>
        <form method="post" action="<?= $this->e($this->url('/admin/content/move')) ?>">
            <input type="hidden" name="id" value="<?= $this->e($id) ?>">
            <label>Новый путь <input type="text" name="to" value="<?= $this->e($id) ?>"></label>
            <button type="submit">Переместить</button>
        </form>
    </section>

    <p><a href="<?= $this->e($this->url('/admin/content/history?id=' . rawurlencode($id))) ?>">История версий</a></p>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', [
    'content' => $content,
    'title' => $isNew ? 'Новый элемент' : 'Редактирование',
    'admin_user' => $admin_user ?? null,
]);
