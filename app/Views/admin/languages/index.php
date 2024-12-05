<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container">
    <a href="/admin/languages/create" class="btn btn-primary mb-3">Add New Language</a>
    <?php if (!empty($languages)): ?>
        <ul class="list-group">
            <?php foreach ($languages as $language): ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <img src="<?= esc($language['flag']) ?>" alt="<?= esc($language['name']) ?> flag" style="width: 24px; height: 16px; margin-right: 8px;">
                            <?= esc($language['name']) ?> (<?= esc($language['code']) ?>)
                        </span>
                        <div>
                            <a href="/admin/languages/edit/<?= $language['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="/admin/languages/delete/<?= $language['id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No languages found.</p>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>