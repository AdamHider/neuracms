<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h1>Media Manager</h1>

<?php if (session()->get('success')): ?>
    <div id="successMessage" class="alert alert-success">
        <?= session()->get('success') ?>
    </div>
<?php endif; ?>

<?php if (session()->get('errors')): ?>
    <div id="errorMessage" class="alert alert-danger">
        <?php foreach (session()->get('errors') as $error): ?>
            <p><?= $error ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<!-- Навигация по директориям -->
<h2 class="mt-5">Media Files</h2>
<div id="media-files">
    <?= view('admin/media/file_list', ['currentDir' => $currentDir, 'files' => $files]) ?>
</div>

<script>
    function loadDirectory(dir) {
        fetch(`<?= site_url('admin/media/getFileList') ?>/${dir}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('media-files').innerHTML = html;
                setEvents()
            });
    }

    function deleteFile(file) {
        if (confirm('Are you sure you want to delete this file?')) {
            fetch(`<?= site_url('admin/media/deleteFile') ?>/${file}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('media-files').innerHTML = html;
                    setEvents()
                });
        }
    }

    function deleteDirectory(dir) {
        if (confirm('Are you sure you want to delete this directory?')) {
            fetch(`<?= site_url('admin/media/deleteDirectory') ?>/${dir}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('media-files').innerHTML = html;
                    setEvents()
                });
        }
    }
</script>
<?= $this->endSection() ?>

