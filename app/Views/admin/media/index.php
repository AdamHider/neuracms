<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div id="file_picker"></div>
<script src="<?=base_url('assets/file_explorer/js/main.js')?>"></script>
<link rel="stylesheet" href="<?php echo base_url('assets/file_explorer/css/main.css')?>" type="text/css">
<script>
    const basePath = "<?=base_url('admin/media/')?>";
    initFileExplorer({
        filePickerElement: '#file_picker'
    });
</script>
<?= $this->endSection() ?>
