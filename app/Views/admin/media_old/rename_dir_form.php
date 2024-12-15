<!-- Модальное окно для переименования директории -->
<div class="modal fade" id="renameDirModal" tabindex="-1" aria-labelledby="renameDirModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameDirModalLabel">Rename Directory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?= form_open(action: "/admin/media/renameDirectory/$currentDir") ?>
                    <input type="hidden" name="oldName" id="oldDirName">
                    <div class="form-group">
                        <label for="newDirName">New Directory Name</label>
                        <input type="text" name="newName" id="newDirName" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-secondary mt-2">Rename</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>