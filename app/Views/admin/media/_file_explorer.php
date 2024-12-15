<div class="card mb-3">
    <div class="d-flex align-items-center highlight-toolbar ps-3 pe-2 py-1 border-0 border-bottom">  
        <div class="d-flex me-2">
            <button class="btn btn-light ms-2 button-back" data-dir=""><i class="bi bi-arrow-left"></i></button>
        </div>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb nav m-0" id="breadcrumbs"></ul>
        </nav>
        <div class="d-flex ms-auto">
            <div id="selectedFiles" class="d-flex"></div>
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload"></i></button>
            <button class="btn btn-secondary ms-2" data-bs-toggle="modal" data-bs-target="#createDirModal"><i class="bi bi-folder-plus"></i></button>
        </div>
    </div>
    <div class="card-body">
        <div id="media-files"></div>
    </div>
</div>
<div id="modals">
    <?= view('admin/media/upload_form') ?>
    <?= view('admin/media/create_dir_form') ?>
    <?= view('admin/media/rename_form') ?>
</div>
<script src="<?=base_url('assets/file_explorer/js/render.js')?>"></script>
<script src="<?=base_url('assets/file_explorer/js/data.js')?>"></script>
<script src="<?=base_url('assets/file_explorer/js/main.js')?>"></script>
<link rel="stylesheet" href="<?php echo base_url('assets/file_explorer/css/main.css')?>" type="text/css">
