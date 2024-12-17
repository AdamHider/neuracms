
<div class="modal fade" id="addToFavouritesModal" aria-hidden="true" aria-labelledby="addToFavouritesModalLabel" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="addToFavouritesModalLabel">Add to favourites</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newTemplateName" class="form-label">Name</label>
                    <input type="text" class="form-control"  name="new-template-name" id="newTemplateName" placeholder="">
                </div>
                <div class="mb-3">
                    <label for="newTemplateGroup" class="form-label">Group</label>
                    <input type="text" class="form-control"  name="new-template-group" id="newTemplateGroup" placeholder="">
                </div>
                <input type="hidden" name="new-template-json">
                <div class="alert alert-danger invisible" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Add to favourites</button>
            </div>
        </div>
    </div>
</div>