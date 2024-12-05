<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container">
    <form action="/admin/languages/store" method="post">
        <div class="mb-3">
            <label for="name" class="form-label">Language Name</label>
            <input type="text" name="name" class="form-control" id="name" value="<?= set_value('name') ?>" required>
            <?php if(isset(session()->getFlashdata('errors')['name'])): ?>
                <div class="alert alert-danger mt-2">
                    <?= session()->getFlashdata('errors')['name'] ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label for="code" class="form-label">Language Code</label>
            <input type="text" name="code" class="form-control" id="code" value="<?= set_value('code') ?>" required>
            <?php if(isset(session()->getFlashdata('errors')['code'])): ?>
                <div class="alert alert-danger mt-2">
                    <?= session()->getFlashdata('errors')['code'] ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label for="flag" class="form-label">Flag URL</label>
            <input type="text" name="flag" class="form-control" id="flag" value="<?= set_value('flag') ?>">
            <?php if(isset(session()->getFlashdata('errors')['flag'])): ?>
                <div class="alert alert-danger mt-2">
                    <?= session()->getFlashdata('errors')['flag'] ?>
                </div>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Add Language</button>
    </form>
</div>
<?= $this->endSection() ?>