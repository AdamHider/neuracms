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
            <div id="selectedFiles" class="d-flex invisible">
                <b></b>
                <button class="btn btn-success ms-2 select-file" data-file="">Select</button>
                <button class="btn btn-primary ms-2" data-bs-toggle="dropdown" data-name="" data-dir="">Rename</button>
                <div class="dropdown-menu px-4 py-3">
                    <?= view('admin/media/rename_form', ['currentDir' => $currentDir]) ?>
                </div>
                <button class="btn btn-primary ms-2 delete-file" data-file="">Delete</button>
            </div>
            <button class="btn btn-primary ms-2" data-bs-toggle="dropdown"><i class="bi bi-upload"></i></button>
            <div class="dropdown-menu px-4 py-3">
                <?= view('admin/media/upload_form', ['currentDir' => $currentDir]) ?>
            </div>
            <button class="btn btn-secondary ms-2" data-bs-toggle="dropdown"><i class="bi bi-folder-plus"></i></button>
            <div class="dropdown-menu px-4 py-3">
                <?= view('admin/media/create_dir_form', ['currentDir' => $currentDir]) ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="media-files">
            <?= view('admin/media/_file_explorer_directory', ['files' => $files, 'currentDir' => $currentDir]) ?>
        </div>
    </div>
</div>