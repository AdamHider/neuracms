<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container">
    <a href="/admin/menus/create" class="btn btn-primary mb-3">Create New Menu Item</a>

    <h2>Menu Items</h2>
    <?php if (!empty($items)): ?>
        <ul class="list-group">
            <?php foreach ($items as $item): ?>
                <?= view('admin/menus/_menu_item', ['item' => $item]) ?>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No menu items found.</p>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
