<?php
/**
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<div class="panel">
    <p class="mb-2"><?= $this->icon('warning', 'text-warning') ?> Элемент не найден.</p>
    <a class="btn btn-primary btn-sm" href="<?= $this->e($this->url('/admin/content')) ?>"><?= $this->icon('content') ?> К списку контента</a>
</div>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Не найдено', 'active' => 'content', 'admin_user' => $admin_user ?? null]);
