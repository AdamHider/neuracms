<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the page <strong id="modal-page-title"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirm-delete-btn">Delete</a>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var pageId = button.getAttribute('data-page-id');
            var pageTitle = button.getAttribute('data-page-title');
            
            var modalTitle = deleteModal.querySelector('#modal-page-title');
            var confirmDeleteBtn = deleteModal.querySelector('#confirm-delete-btn');
            
            modalTitle.textContent = pageTitle;
            confirmDeleteBtn.href = '/admin/pages/delete/' + pageId;
        });
    });
</script>