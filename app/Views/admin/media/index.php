<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div id="file_picker">
    <?= view('admin/media/_file_explorer') ?>
</div>
<script>
    const basePath = "<?=base_url('admin/media/')?>";
    initFileExplorer({
        filePickerElement: '#file_picker',
        multipleMode: false,
        pickerMode: true
    });
</script>
<?= $this->endSection() ?>
