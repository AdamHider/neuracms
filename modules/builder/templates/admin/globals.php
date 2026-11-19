<?php
/**
 * Reusable sections and blocks ("globals"). Anything promoted here can be
 * dropped into any page, so the site header is edited once and appears
 * everywhere it is used.
 *
 * @var array<string, array<string, array{name: string, title: string, kind: string, role: string, order: int}>> $globals
 * @var array<string, int> $usage  reference counts keyed by "kind:name"
 * @var bool $saved
 * @var string $error
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
$groups = [
    \FlatCms\Modules\Builder\GlobalLibrary::SECTION => ['title' => 'Глобальные секции', 'icon' => 'section', 'hint' => 'Шапка, подвал и любые другие полосы, которые повторяются на страницах.'],
    \FlatCms\Modules\Builder\GlobalLibrary::BLOCK => ['title' => 'Глобальные элементы', 'icon' => 'grid', 'hint' => 'Отдельные блоки: кнопка, меню, текст, которые удобно переиспользовать.'],
];
$roleLabels = [
    'header' => 'шапка',
    'footer' => 'подвал',
    'main' => 'основная часть',
];
ob_start();
?>
<?php if (!empty($saved)): ?><div class="alert alert-success"><?= $this->icon('check') ?> Сохранено.</div><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><div class="alert alert-danger"><?= $this->icon('warning') ?> <?= $this->e($error) ?></div><?php endif; ?>

<p class="text-body-secondary">
    Глобальная секция или элемент существует один раз и подключается на любой странице.
    Изменение здесь сразу видно везде, где он используется.
</p>

<?php foreach ($groups as $kind => $meta): ?>
    <?php $items = $globals[$kind] ?? []; ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <?= $this->icon($meta['icon']) ?>
            <strong><?= $this->e($meta['title']) ?></strong>
            <span class="badge text-bg-secondary"><?= count($items) ?></span>
        </div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3"><?= $this->e($meta['hint']) ?></p>

            <?php if ($items === []): ?>
                <p class="text-body-secondary mb-0">
                    Пока пусто. Откройте страницу в
                    <a href="<?= $this->e($this->url('/admin/pages')) ?>">конструкторе</a>
                    и нажмите «Сделать глобальной» у нужной секции или элемента.
                </p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($items as $item): ?>
                        <?php $count = (int) ($usage[$kind . ':' . $item['name']] ?? 0); ?>
                        <li class="list-group-item d-flex flex-wrap align-items-center gap-2 px-0">
                            <div class="me-auto">
                                <div class="fw-semibold">
                                    <?= $this->e($item['title']) ?>
                                    <?php if (($item['role'] ?? '') !== ''): ?>
                                        <span class="badge text-bg-info"><?= $this->e($roleLabels[$item['role']] ?? $item['role']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-body-secondary small">
                                    <code><?= $this->e($item['name']) ?></code>
                                    · используется: <?= $count ?>
                                </div>
                            </div>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($this->url('/admin/builder?global=' . rawurlencode($kind . ':' . $item['name']))) ?>">
                                <?= $this->icon('builder') ?> Редактировать
                            </a>
                            <form method="post" action="<?= $this->e($this->url('/admin/builder/global')) ?>" onsubmit="return confirm('Удалить глобальный элемент? Страницы, которые его используют, потеряют его.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="global" value="<?= $this->e($kind . ':' . $item['name']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= $this->icon('delete') ?> Удалить</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', [
    'content' => $content,
    'title' => 'Глобальные секции',
    'active' => 'globals',
    'admin_user' => $admin_user ?? null,
]);
