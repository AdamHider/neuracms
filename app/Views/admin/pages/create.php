<?= $this->extend('layouts/'.$this->data['settings']['layout']) ?>
<?= $this->section('content') ?>
<div class="container">
    <form action="/admin/pages/store" method="post">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" class="form-control" id="title" required>
        </div>
        <div class="mb-3">
            <label for="content" class="form-label">Content</label>
            <textarea name="content" class="form-control" id="content" rows="10" required></textarea>
        </div>
        <?php if(isset($this->data['validation'])): ?>
            <div class="alert alert-danger">
                <?= $validation->listErrors() ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
</div>
<?= $this->endSection() ?>
