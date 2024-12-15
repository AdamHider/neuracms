
<!-- Кнопки для открытия модальных окон -->
<button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload Media</button>
<button class="btn btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#createDirModal">Create Directory</button>

<!-- Включение форм -->
<?= view('admin/media/upload_form', ['currentDir' => $currentDir]) ?>
<?= view('admin/media/create_dir_form', ['currentDir' => $currentDir]) ?>
<?= view('admin/media/rename_file_form', ['currentDir' => $currentDir]) ?>
<?= view('admin/media/rename_dir_form', ['currentDir' => $currentDir]) ?>
<ul class="list-group">
    <?php if ($currentDir !== ''): ?>
        <li class="list-group-item">
            <a href="#" class="nav-link" data-dir="<?= dirname($currentDir) ?>">.. (Parent Directory)</a>
        </li>
    <?php endif; ?>

    <?php foreach ($files as $item): ?>
        <li class="list-group-item">
            <?php if (is_dir(WRITEPATH . 'uploads/media/' . $currentDir . '/' . $item)): ?>
                <a href="#" class="nav-link" data-dir="<?= $currentDir . '/' . $item ?>">[DIR] <?= $item ?></a>
                <a href="#" class="btn btn-danger btn-sm float-end delete-dir" data-dir="<?= $currentDir . '/' . $item ?>">Delete</a>
                <button class="btn btn-sm btn-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#renameDirModal" onclick="setRenameDirModalValues('<?= $item ?>')">Rename</button>
            <?php else: ?>
                [FILE] <?= $item ?>
                <?php if (strpos(mime_content_type(WRITEPATH . 'uploads/media/' . $currentDir . '/' . $item), 'image') !== false): ?>
                    <img src="<?= site_url('image/' . urlencode($currentDir . '/' . $item)) ?>" alt="<?= $item ?>" class="img-thumbnail" style="max-width: 100px;">
                <?php endif; ?>
                <a href="#" class="btn btn-danger btn-sm float-end delete-file" data-file="<?= $currentDir . '/' . $item ?>">Delete</a>
                <button class="btn btn-sm btn-warning float-end me-2" data-bs-toggle="modal" data-bs-target="#renameFileModal" onclick="setRenameFileModalValues('<?= $item ?>')">Rename</button>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        setEvents()
    });
    function setEvents(){

        // Обработчик кликов на ссылки навигации
        document.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                loadDirectory(link.dataset.dir);
            });
        });

        // Обработчик кликов на кнопки удаления файла
        document.querySelectorAll('.delete-file').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                deleteFile(button.dataset.file);
            });
        });

        // Обработчик кликов на кнопки удаления директории
        document.querySelectorAll('.delete-dir').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                deleteDirectory(button.dataset.dir);
            });
        });
    }
</script>
