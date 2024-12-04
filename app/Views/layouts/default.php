<?= $this->extend('layouts/index') ?>

<?= $this->section('main') ?>
<div class="container-fluid">
    <div class="row flex-nowrap">
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