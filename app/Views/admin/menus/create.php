<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
    <div class="container">
        <form action="/admin/menus/store" method="post">
            <div class=" mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" name="title" class="form-control" id="title" value="<?= esc('title') ?>" required>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">URL</label>
                <input type="text" name="url" class="form-control" id="url" value="<?= esc('url') ?>" required>
            </div>
            <div class="mb-3">
                <label for="order" class="form-label">Order</label>
                <input type="number" name="order" class="form-control" id="order" value="<?= esc('order') ?>" required>
            </div>
            <div class="mb-3">
                <label for="parent_id" class="form-label">Parent Menu Item</label>
                <select name="parent_id" class="form-control" id="parent_id">
                    <option value="">None</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= $parent_id == $item['id'] ? 'selected' : '' ?>><?= esc($item['title']) ?></option>
                        <?php endforeach; ?>
                </select>
            </div>
            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Create</button>
        </form>
    </div>
<?= $this->endSection() ?>
