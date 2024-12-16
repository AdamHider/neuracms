<form id="renameForm">
    <input type="hidden" id="rename-dir" name="dir" value="<?=$currentDir?>">
    <input type="hidden" id="rename-oldName" name="oldName">
    <div class="form-group">
        <label for="rename-newName">New Name</label>
        <input type="text" name="newName" id="rename-newName" class="form-control">
    </div>
    <button type="submit" class="btn btn-secondary mt-2">Rename</button>
</form>
