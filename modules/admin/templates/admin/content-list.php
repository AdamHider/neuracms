<?php
/**
 * @var array $items
 * @var string $type
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<p>
    <a class="button" href="<?= $this->e($this->url('/admin/content/new')) ?>">Добавить элемент</a>
</p>

<table class="table">
    <thead>
    <tr><th>ID</th><th>Заголовок</th><th>Тип</th><th>Опубликован</th><th>Действия</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><code><?= $this->e($item->id()) ?></code></td>
            <td><?= $this->e($item->title()) ?></td>
            <td><?= $this->e($item->type()) ?></td>
            <td><?= $item->published() ? 'да' : 'нет' ?></td>
            <td class="actions">
                <a href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($item->id()))) ?>">Изменить</a>
                <a href="<?= $this->e($this->url('/admin/content/history?id=' . rawurlencode($item->id()))) ?>">История</a>
                <form method="post" action="<?= $this->e($this->url('/admin/content/delete')) ?>"
                      onsubmit="return confirm('Удалить элемент?');">
                    <input type="hidden" name="id" value="<?= $this->e($item->id()) ?>">
                    <button type="submit" class="danger">Удалить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Контент', 'admin_user' => $admin_user ?? null]);
