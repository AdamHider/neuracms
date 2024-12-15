<div class="card mb-3">
    <div class="d-flex align-items-center highlight-toolbar ps-3 pe-2 py-1 border-0 border-bottom">  
        <div class="d-flex me-2">
            <?php if($currentDir !== '') : ?>
            <button class="btn btn-light ms-2 button-back" data-dir="<?=$currentDir?>"><i class="bi bi-arrow-left"></i></button>
            <?php endif; ?>
        </div>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb nav m-0" id="breadcrumbs">
                <li class="breadcrumb-item"><a href="#" class="dir-link" data-dir="/">Home</a></li>
                <?php foreach(explode('/', $currentDir) as $index => $part) : ?>
                    <?php if($index === count(explode('/', $currentDir)) - 1) : ?>
                        <li class="breadcrumb-item active"><?=$part?></li>
                    <?php else : ?>
                        <li class="breadcrumb-item"><a href="#" class="dir-link" data-dir="<?=$part?>"><?=$part?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="d-flex ms-auto">
            <div id="selectedFiles" class="d-flex"></div>
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload"></i></button>
            <button class="btn btn-secondary ms-2" data-bs-toggle="modal" data-bs-target="#createDirModal"><i class="bi bi-folder-plus"></i></button>
        </div>
    </div>
    <div class="card-body">
        <div id="media-files">
            <?= view('admin/media/_file_explorer_directory', ['files' => $files, 'currentDir' => $currentDir]) ?>
        </div>
    </div>
</div>
<div id="modals">
    <?= view('admin/media/upload_form', ['currentDir' => $currentDir]) ?>
    <?= view('admin/media/create_dir_form', ['currentDir' => $currentDir]) ?>
    <?= view('admin/media/rename_form', ['currentDir' => $currentDir]) ?>
</div>
