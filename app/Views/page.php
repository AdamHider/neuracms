<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container mt-5">
    <div>
        <?= $page['content'] ?>
    </div>
</div>
<?= $this->endSection() ?>