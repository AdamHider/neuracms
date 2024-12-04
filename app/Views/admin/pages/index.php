<?= $this->extend('layouts/'.$this->data['settings']['layout']) ?>
<?= $this->section('content') ?>
<div class="container ">
    <a href="/admin/pages/create" class="btn btn-primary mb-3"><i class="bi bi-file-earmark-plus me-2"></i> Create New Page</a>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->data['pages'] as $page): ?>
                <tr>
                    <td><?= esc($page['title']) ?></td>
                    <td>
                        <a href="/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="/admin/pages/delete/<?= $page['id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>