<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<h1>AJAX File Explorer</h1>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb" id="breadcrumbs"></ol>
</nav>

<!-- Кнопки для открытия модальных окон -->
<button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload Media</button>
<button class="btn btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#createDirModal">Create Directory</button>

<!-- Включение форм -->
<div id="modals">
    <?= view('admin/file_explorer/upload_form') ?>
    <?= view('admin/file_explorer/create_dir_form') ?>
    <?= view('admin/file_explorer/rename_form') ?>
</div>

<!-- Навигация по директориям -->
<h2 class="mt-5">Media Files</h2>
<div id="media-files"></div>

<script src="<?=base_url('assets/file_explorer/file_explorer.js')?>"></script>
<?= $this->endSection() ?>
