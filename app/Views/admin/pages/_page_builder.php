<div class="container-fluid">
        <div class="row g-2 justify-content-between">
            <div class="col-auto">
                <div id="toolbar" class="card sticky-top sticky-offset">
                    <div class="d-flex flex-column flex-shrink-0">
                        <ul class="nav nav-pills nav-flush flex-column mb-auto text-center">
                            <li class="nav-item">
                                <button class="btn btn-light m-2"  type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasComponents" aria-controls="offcanvasComponents">
                                    <i class="bi bi-puzzle"></i>
                                </button>
                            </li>
                            <li>
                                <input type="checkbox" class="btn-check" id="workspace_marked_toggle" autocomplete="off" checked="checked">
                                <label class="btn btn-light m-2 mt-0" for="workspace_marked_toggle"><i class="bi bi-border-outer"></i></label>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-8">
                <div id="workspace" class="workspace-marked bg-white" tabindex="-1">
                    <!-- Рабочая область для конструктора страниц -->
                </div>
                <input type="hidden" name="json_content" id="json_content">
            </div>
            <div id="sidebar" class="col-3 d-flex flex-column flex-shrink-0">
                    <div class="card sticky-top sticky-offset border">
                        <div class="card-header">
                            <div class="d-flex justify-content-between">
                                <h4 id="sidebar-title" class="card-title">Element Properties</h4>
                                <a class="btn invisible" id="addToFavourites" role="button" data-bs-toggle="modal" href="#addToFavouritesModal"><i class="bi bi-star"></i></a>
                            </div>

                        </div>
                        <div class="card-body p-0" id="properties-container"></div>
                    </div>
            </div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" data-bs-scroll="true" data-bs-backdrop="false" id="offcanvasComponents" aria-labelledby="offcanvasComponentsLabel"></div>
</div>

<?= view('admin/pages/_add_to_favourites_modal') ?>

<link rel="stylesheet" href="<?php echo base_url('assets/jquery-ui/jquery-ui.min.css')?>" type="text/css">
<script type="text/javascript" src="<?php echo base_url('assets/jquery-ui/jquery-ui.min.js')?>"></script>

<link rel="stylesheet" href="<?php echo base_url('assets/colorpicker/css/colorpicker.css')?>" type="text/css">
<script type="text/javascript" src="<?php echo base_url('assets/colorpicker/js/colors.js')?>"></script>
<script type="text/javascript" src="<?php echo base_url('assets/colorpicker/js/colorpicker.js')?>"></script>



<script>
var pageData = <?= isset($page['json_content']) ? json_encode(json_decode($page['json_content']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
let isChanged = false

$('#workspace_marked_toggle').on('change', (e) => {
    if($(e.target).is(":checked")) return $('#workspace').addClass("workspace-marked");
    $('#workspace').removeClass("workspace-marked");
})
$('#toolbar').on('click', (e) => {
    $('.active-element').removeClass('active-element');
})
</script>
<script src="<?=base_url('assets/pagebuilder/dist/fields.js')?>"></script>
<script src="<?=base_url('assets/pagebuilder/dist/properties.js')?>"></script>
<script src="<?=base_url('assets/pagebuilder/dist/render.js')?>"></script>
<script src="<?=base_url('assets/pagebuilder/dist/data.js')?>"></script>
<script src="<?=base_url('assets/pagebuilder/dist/main.js')?>"></script>

<link rel="stylesheet" href="<?= useStyle('assets/pagebuilder/css/main.scss'); ?>">


<div class="modal modal-xl" id="pickerModal" tabindex="-1" aria-hidden="true"  aria-labelledby="imagePickerModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePickerModalLabel">Choose an image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                    <div id="file_picker"></div>
            </div>
        </div>
    </div>
</div>
<script src="<?=base_url('assets/file_explorer/js/main.js')?>"></script>
<link rel="stylesheet" href="<?php echo base_url('assets/file_explorer/css/main.css')?>" type="text/css">
<script>
    const basePath = "<?=base_url('admin/media/')?>";
</script>


