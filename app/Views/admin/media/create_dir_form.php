<div class="modal fade" id="createDirModal" tabindex="-1" aria-labelledby="createDirModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createDirModalLabel">Create Directory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createDirForm">
                    <input type="hidden" id="create-dir" name="dir" value="<?=$currentDir?>">
                    <div class="form-group">
                        <label for="dirname">Directory Name</label>
                        <input type="text" name="dirname" id="dirname" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-secondary mt-2">Create</button>
                </form>
            </div>
        </div>
    </div>
</div>
