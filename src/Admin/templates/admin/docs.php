<?php
/**
 * In-admin documentation viewer. Lists docs/*.md on the left and renders the
 * chosen one on the right.
 *
 * @var array<string, string> $docs
 * @var string $current
 * @var string $doc_title
 * @var string $doc_html
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<div class="docs">
    <aside class="docs__nav panel">
        <h2><?= $this->icon('docs') ?> Разделы</h2>
        <ul class="docs__list">
            <?php foreach ($docs as $name => $title): ?>
                <li>
                    <a class="docs__link<?= $current === $name ? ' is-active' : '' ?>"
                       href="<?= $this->e($this->url('/admin/docs?page=' . rawurlencode($name))) ?>">
                        <?= $this->e($title) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <article class="docs__body panel">
        <?php if ($current === ''): ?>
            <p class="muted">Выберите раздел слева.</p>
        <?php else: ?>
            <div class="docs__prose"><?= $doc_html ?></div>
        <?php endif; ?>
    </article>
</div>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Документация', 'active' => 'docs', 'admin_user' => $admin_user ?? null]);
