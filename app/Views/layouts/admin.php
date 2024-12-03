<?= $this->extend('layouts/main') ?>

<?= $this->section('body') ?>
<div class="container-fluid">
    <div class="row flex-nowrap">
        <div class="col-auto col-md-3 col-xl-2  px-0 bg-dark">
            <div class="flex-column flex-shrink-0 p-3 text-white min-vh-100">
                <a href="/" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                    <span class="fs-5 d-none d-sm-inline">Menu</span>
                    <p>Hello, <?= session()->get('username'); ?>!</p>
                    <a href="/auth/logout">Logout</a>

                </a>
                <?= view_cell('App\Cells\Menu::render', ['data' => [
                    'menu' => $this->data['settings']['menu'], 
                    'current_uri' => $this->data['settings']['path']
                    ]
                ]) ?>
            </div>
        </div>
        <div class="col py-3">
            <?php if(!empty($this->data['sections'])) : ?>
            <?php foreach($this->data['sections'] as $section_name => $section) : ?>
                <section id="<?= $section_name ?>">
                    <div class="container px-5">
                    <?php foreach($section['rows'] as $row) : ?>
                        <div class="row gx-5">
                            <?php foreach($row['cols'] as $cell) : ?>
                                <div class="col-<?= $cell['width']??'12' ?>">
                                    <?= view_cell('App\Cells\\'.$cell['name'].'::render', $cell) ?>
                                </div>
                            <?php endforeach ?>   
                        </div>
                    <?php endforeach ?>  
                    </div>
                </section> 
            <?php endforeach ?>   
            <?php endif ?>
            <section id="page_content">
                <div class="content"><?= $this->renderSection('content') ?></div>
            </section> 
        </div>
    </div>
</div>
<?= $this->endSection() ?>