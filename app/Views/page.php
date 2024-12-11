<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container">
    <div>
        <?= $page['content'] ?>
    </div>
</div>
<?= $this->endSection() ?>