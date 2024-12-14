<?= $this->extend('layouts/'.$settings['layout']) ?>
<?= $this->section('content') ?>
<div class="container px-0">
    <form action="/admin/pages/<?= $action ?>" method="post">
    
    <header class="p-3 border-bottom bg-white">
        <div class="d-flex  justify-content-between align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
            <!-- Header Section -->
            <div>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <input type="text" name="title" class="form-control" id="title" value="<?= set_value('title', $page['title']) ?>" placeholder="Title" required>
                    </div>
                    <div class="me-3">
                        <input type="text" name="slug" class="form-control" id="slug" value="<?= set_value('slug', $page['slug']) ?>" placeholder="Generated from title">
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMeta" aria-expanded="false" aria-controls="collapseMeta">
                            Settings
                        </button>
                    </div>
                </div>
            </div>
            <div>
                <?php if (isset($preview_link)) : ?>
                <a href="<?=base_url($preview_link)  ?>" class="btn btn-info" target="_blank">Preview</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" disabled>Save</button>
            </div>
        </div>
        <div class="collapse mt-2" id="collapseMeta">
            <div class="row g-3">
                <div class="col">
                    <label for="meta_description" class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-control" id="meta_description" rows="2"><?= set_value('meta_description', $page['meta_description']) ?></textarea>
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
    
    <?php if (session()->getFlashdata('status')): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <strong>Success!</strong> <?= session()->getFlashdata('status') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <strong>Warning!</strong> 
            <ul>
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <!-- Sidebar and Content Section -->
    <div class="d-flex my-2">
        <!-- Content -->
        <?= view('admin/pages/_page_builder') ?>
    </div>

    </form>
</div>
<script>
    $('form button:not([type="submit"])').on('click', (e) => {
        e.preventDefault();
    })
    $('form button[type="submit"]').on('click', (e) => {
        $(window).off('beforeunload')
    })
    $('input').on('change', () => {
        $('form button[type="submit"]').prop('disabled', false)
        $(window).on('beforeunload', function(e){
            return 'You have unsaved changes. Are you sure?';
        });
    });
</script>
<?= $this->endSection() ?>
