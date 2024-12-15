<div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameModalLabel">Rename</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="renameForm">
                    <input type="hidden" id="rename-dir" name="dir" value="<?=$currentDir?>">
                    <input type="hidden" id="rename-oldName" name="oldName">
                    <div class="form-group">
                        <label for="rename-newName">New Name</label>
                        <input type="text" name="newName" id="rename-newName" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-secondary mt-2">Rename</button>
                </form>
            </div>
        </div>
    </div>
</div>
