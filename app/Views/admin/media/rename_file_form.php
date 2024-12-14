<!-- Модальное окно для переименования файла -->
<div class="modal fade" id="renameFileModal" tabindex="-1" aria-labelledby="renameFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameFileModalLabel">Rename File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?= form_open("/admin/media/renameFile/$currentDir") ?>
                    <input type="hidden" name="oldName" id="oldFileName">
                    <div class="form-group">
                        <label for="newFileName">New File Name</label>
                        <input type="text" name="newName" id="newFileName" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-secondary mt-2">Rename</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>