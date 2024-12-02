
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $this->data['page_title'] ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/bootstrap/css/bootstrap.css')?>" type="text/css">
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script type="text/javascript" src="<?php echo base_url('assets/bootstrap/js/bootstrap.js')?>"></script>
</head>
<body>
    <?= $this->include('partials/offcanvas') ?>
    <header>
        <?= view_cell('App\Cells\Navigation::render') ?>
    </header>
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
    <section id="page_content">
        <div class="content"><?= $this->renderSection('content') ?></div>
    </section> 
</body>
</html>