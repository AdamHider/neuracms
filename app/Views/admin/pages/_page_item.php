<li class="list-group-item">
    <div class="d-flex justify-content-between align-items-center">
        <span><?= esc($page['title']) ?> (<?= esc($page['slug']) ?>)</span>
        <div>
            <a href="/admin/pages/form/<?= $page['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-page-id="<?= $page['id'] ?>" data-page-title="<?= esc($page['title']) ?>">
                Delete
            </button>
            <a href="/admin/pages/create?parent_id=<?= esc($page['id']) ?>" class="btn btn-success btn-sm">Add Child</a>
        </div>
    </div>
    <?php if (!empty($page['children'])): ?>
        <ul class="list-group mt-2">
            <?php foreach ($page['children'] as $child): ?>
                <?= view('admin/pages/_page_item', ['page' => $child]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
