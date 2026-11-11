<?php
/**
 * Theme regions. Each region holds a builder layout for a slot of the site
 * chrome (header, footer, …). Editing opens the same builder as a page.
 *
 * @var array<string, string> $regions
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$builderReady = $app->module('builder') !== null;
ob_start();
?>
<p class="muted">Области — это части сайта вокруг страниц: шапка и подвал. Их содержимое задаётся теми же частицами, что и на страницах, но показывается на всех страницах сайта.</p>

<div class="panel p-0">
<table class="table table-hover align-middle mb-0">
    <thead>
    <tr><th>Область</th><th>Содержимое</th><th class="text-end">Действия</th></tr>
    </thead>
    <tbody>
    <?php foreach ($regions as $name => $label): ?>
        <?php $count = $builderReady ? count($app->regions->layout($name)['sections'] ?? []) : 0; ?>
        <tr>
            <td><strong><?= $this->e($label) ?></strong> <code class="muted"><?= $this->e($name) ?></code></td>
            <td>
                <?php if (!$builderReady): ?>
                    <span class="muted">Модуль конструктора выключен</span>
                <?php elseif ($count === 0): ?>
                    <span class="muted">По умолчанию темы (пусто)</span>
                <?php else: ?>
                    <span class="badge text-bg-light"><?= (int) $count ?> секц.</span>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <?php if ($builderReady): ?>
                    <a class="btn btn-sm btn-primary" href="<?= $this->e($this->url('/admin/builder?region=' . rawurlencode($name))) ?>"><?= $this->icon('builder') ?> Редактировать</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($builderReady): ?>
    <p class="muted">Совет: добавьте частицу «Меню», чтобы шапка и подвал ссылались на пункты меню, которые вы ведёте в разделе «Элементы».</p>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Области сайта', 'active' => 'regions', 'admin_user' => $admin_user ?? null]);
