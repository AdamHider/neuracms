<?php
/**
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<p>Элемент не найден.</p>
<p><a href="<?= $this->e($this->url('/admin/content')) ?>">К списку контента</a></p>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Не найдено', 'admin_user' => $admin_user ?? null]);
