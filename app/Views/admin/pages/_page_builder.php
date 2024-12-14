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
                    <div class="card sticky-top sticky-offset border overflow-auto">
                        <div class="card-header">
                            <h4 id="sidebar-title" class="card-title">Element Properties</h4>
                        </div>
                        <div class="card-body p-0" id="properties-container">
                            <!-- Поля свойств будут добавляться здесь -->
                        </div>
                    </div>
            </div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" data-bs-scroll="true"   data-bs-backdrop="false"  id="offcanvasComponents" aria-labelledby="offcanvasComponentsLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasComponentsLabel">Offcanvas right</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div id="available-components-container">
                <div id="available-components" class="d-flex flex-column">
                    <?php foreach ($components as $componentGroup): ?>
                        <div class="mb-4">
                            <h5><?= ucfirst($componentGroup['title']) ?></h5>   
                            <div class="row g-2">
                                <?php foreach ($componentGroup['children'] as $component): ?>
                                <?php if(isset($component['config']['is_private']) && $component['config']['is_private']) { continue; } ?>
                                <div class="col-6">
                                    <div class="card text-center component-item <?= $component['config']['type'] ?>-component" data-type="<?= $component['config']['type'] ?>" data-code="<?= $component['config']['code'] ?>">
                                        <div class="card-body px-1 py-2">
                                            <h4><i class="bi bi-<?= $component['config']['icon'] ?>"></i></h4>
                                            <span><?= ucfirst($component['config']['name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo base_url('assets/jquery-ui/jquery-ui.min.css')?>" type="text/css">
<script type="text/javascript" src="<?php echo base_url('assets/jquery-ui/jquery-ui.min.js')?>"></script>

<link rel="stylesheet" href="<?php echo base_url('assets/colorpicker/css/colorpicker.css')?>" type="text/css">
<script type="text/javascript" src="<?php echo base_url('assets/colorpicker/js/colors.js')?>"></script>
<script type="text/javascript" src="<?php echo base_url('assets/colorpicker/js/colorpicker.js')?>"></script>



<script>
var pageData = <?= isset($page['json_content']) ? json_encode(json_decode($page['json_content']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
const components = <?= isset($components) ? json_encode($components) : '[]' ?>;
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


