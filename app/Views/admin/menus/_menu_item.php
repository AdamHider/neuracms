<li class="list-group-item">
    <div class="d-flex justify-content-between align-items-center">
        <span><?= esc($item['title']) ?> (<?= esc($item['url']) ?>)</span>
        <div>
            <a href="#" class="btn btn-warning btn-sm">Edit</a>
            <a href="#" class="btn btn-danger btn-sm">Delete</a>
            <a href="/admin/menus/create?parent_id=<?= esc($item['id']) ?>" class="btn btn-success btn-sm">Add Child</a>
        </div>
    </div>
    <?php if (!empty($item['children'])): ?>
        <ul class="list-group mt-2">
            <?php foreach ($item['children'] as $child): ?>
                <?= view('admin/menus/_menu_item', ['item' => $child]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
