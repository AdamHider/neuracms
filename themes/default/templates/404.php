<?php
/**
 * @var array $site
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<div class="error-page">
    <h1>404</h1>
    <p>Страница не найдена.</p>
    <p><a href="<?= $this->e($this->url('/')) ?>">На главную</a></p>
</div>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'title' => '404']);
