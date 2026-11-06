<?php
/**
 * @var string $id
 * @var array $entries
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<p><a href="<?= $this->e($this->url('/admin/content/edit?id=' . rawurlencode($id))) ?>">← К элементу</a></p>

<?php if ($entries === []): ?>
    <p>История пуста. Копия создаётся при каждом сохранении.</p>
<?php else: ?>
    <table class="table">
        <thead><tr><th>Сохранено</th><th>Папка</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= $this->e($entry['saved_at']) ?></td>
                <td><code>var/history/<?= $this->e($entry['id']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted">Копии лежат в var/history/ и не отдаются по HTTP.</p>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'История: ' . $id, 'admin_user' => $admin_user ?? null]);
