<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= $settings['title'] ?></title>
        <link rel="stylesheet" href="<?php echo base_url('assets/bootstrap/icons/font/bootstrap-icons.min.css')?>" type="text/css">
        <link rel="stylesheet" href="<?php echo base_url('assets/bootstrap/css/bootstrap.min.css')?>" type="text/css">
        <script type="text/javascript" src="<?php echo base_url('assets/bootstrap/js/bootstrap.bundle.min.js')?>"></script>
        <script type="text/javascript" src="<?php echo base_url('assets/jquery/js/jquery-3.7.1.min.js')?>"></script>
    </head>
    <body>
        <?= $this->renderSection('main') ?>
    </body>
</html>