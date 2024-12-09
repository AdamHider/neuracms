<?= $this->extend('layouts/index') ?>

<?= $this->section('main') ?>
<div class="container-fluid">
    <div class="row flex-nowrap">
        <div class="col-auto col-md-3 col-xl-2  px-0 bg-dark">
            <div class="d-flex flex-column flex-shrink-0 p-3 text-white min-vh-100">
                <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                    <span class="fs-4">NeuraCMS</span>
                </a>
                <hr>
                <div class="flex-column mb-auto">
                    <?= view_cell('App\Cells\Menu::render', ['data' => [
                        'menu' => $settings['menu'], 
                        'current_uri' => $settings['path']
                        ]
                    ]) ?>
                </div>
                <hr>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser21" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-2"></i> 
                        <strong><?= session()->get('username') ?></strong>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser21">
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <li><a class="dropdown-item" href="#">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/auth/logout">Sign out</a></li>
                    </ul>
                </div>
                <div class="mt-3 text-white">
                    <strong>Version:</strong> 1.0.0
                </div>
            </div>
        </div>
        <div class="col ">
                <header class="py-3 mb-3 border-bottom">
                    <div class="d-flex  mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                        <span class="fs-4"><?= $settings['title'] ?></span>
                    </a>
                </header>
                <?php if(!empty($sections)) : ?>
                <?php foreach($sections as $section_name => $section) : ?>
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