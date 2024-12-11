<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container ">
    <a href="/admin/pages/form" class="btn btn-primary mb-3"><i class="bi bi-file-earmark-plus me-2"></i> New Page</a>
    <?php if (!empty($pages)): ?>
        <ul class="list-group">
            <?php foreach ($pages as $page): ?>
                <?= view('admin/pages/_page_item', ['page' => $page]) ?>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No pages found.</p>
    <?php endif; ?>
</div>
<?= view('admin/pages/_delete_modal') ?>
<?= $this->endSection() ?>