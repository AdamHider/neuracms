<?= $this->extend('layouts/'.$this->data['settings']['layout']) ?>
<?= $this->section('content') ?>
<div class="container px-0">
    <form action="/admin/pages/<?= $action ?>" method="post">
    
    <header class="py-3 border-bottom">
        <div class="d-flex  justify-content-between align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
            <!-- Header Section -->
            <div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <input type="text" name="title" class="form-control" id="title" value="<?= set_value('title', $page['title']) ?>" placeholder="Title" required>
                    </div>
                    <div class="me-3">
                        <input type="text" name="slug" class="form-control" id="slug" value="<?= set_value('slug', $page['slug']) ?>" placeholder="Slug" required>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMeta" aria-expanded="false" aria-controls="collapseMeta">
                            Settings
                        </button>
                        
                    </div>
                </div>
            </div>
            <div>
                <button class="btn btn-info">Preview</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
        <div class="collapse mt-2" id="collapseMeta">
            <div class="row g-3">
                <div class="col">
                    <label for="meta_description" class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-control" id="meta_description" rows="2" required><?= set_value('meta_description', $page['meta_description']) ?></textarea>
                </div>
                <div class="col">
                    <label for="language_id" class="form-label">Language</label>
                    <select name="language_id" class="form-control form-select" id="language_id" required>
                        <?php foreach ($languages as $language): ?>
                            <option value="<?= $language['id'] ?>" <?= set_select('language_id', $language['id'], $language['id'] == $page['language_id']) ?>><?= esc($language['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="parent_id" class="form-label">Parent Page</label>
                    <select name="parent_id" class="form-control form-select" id="parent_id">
                        <option value="">None</option>
                        <?php foreach ($pages as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= set_select('parent_id', $p['id'], $p['id'] == $page['parent_id']) ?>><?= esc($p['slug']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </header>
    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <!-- Sidebar and Content Section -->
    <div class="d-flex">

        <!-- Content -->
        <div class="flex-grow-1 ms-3 col col-9">
            
        <?= view('admin/pages/_page_builder') ?>
            <label for="content" class="form-label">Content</label>
            <textarea name="content" class="form-control" id="content" rows="20" required><?= set_value('content', $page['content']) ?></textarea>
            
        </div>
        <!-- Sidebar -->
        <div class="bg-light p-3 col col-3">
            <ul class="list-group">
                <li class="list-group-item">An item</li>
                <li class="list-group-item">A second item</li>
                <li class="list-group-item">A third item</li>
                <li class="list-group-item">A fourth item</li>
                <li class="list-group-item">And a fifth one</li>
            </ul>
        </div>
    </div>

    </form>
</div>
<?= $this->endSection() ?>
