<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container mt-5">
    <h1><?= esc($page['title']) ?></h1>
    <div>
        <?= esc($page['content']) ?>
    </div>
</div>
<?= $this->endSection() ?>