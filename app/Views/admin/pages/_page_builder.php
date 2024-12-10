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
                <div id="workspace" class="workspace-marked container bg-white" tabindex="-1">
                    <!-- Рабочая область для конструктора страниц -->
                </div>
                <input type="hidden" name="json_content" id="json_content">
            </div>
            <div id="sidebar" class="col-3 d-flex flex-column flex-shrink-0">
                    <div class="card rounded-0 sticky-top sticky-offset border overflow-auto">
                        <div class="card-header">
                            <h4 id="sidebar-title" class="card-title">Element Properties</h4>
                        </div>
                        <div class="card-body" id="properties-container">
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
                                <div class="col-6">
                                    <div class="card text-center component-item" data-type="<?= $component['config']['type'] ?>" data-code="<?= $component['config']['code'] ?>">
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

<script>
var pageData = <?= isset($page['json_content']) ? json_encode(json_decode($page['json_content']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
const components = <?= isset($components) ? json_encode($components) : '[]' ?>;

$('#workspace_marked_toggle').on('change', (e) => {
    if($(e.target).is(":checked")) return $('#workspace').addClass("workspace-marked");
    $('#workspace').removeClass("workspace-marked");
})
$('#toolbar').on('click', (e) => {
    $('.active-element').removeClass('active-element');
})
</script>
<script src="<?php echo base_url('assets/pagebuilder/dist/fields.js')?>"></script>
<script src="<?php echo base_url('assets/pagebuilder/dist/properties.js')?>"></script>
<script src="<?php echo base_url('assets/pagebuilder/dist/render.js')?>"></script>
<script src="<?php echo base_url('assets/pagebuilder/dist/data.js')?>"></script>
<script src="<?php echo base_url('assets/pagebuilder/dist/main.js')?>"></script>

<style >
    #offcanvasComponents{
        max-width: 300px;
    }
    
    #workspace { border: 1px solid #ddd; min-height: 300px; padding: 15px; }

    #workspace.workspace-marked .workspace-component{
        border: 1px dashed #6c757d;
    }
    .workspace-component {
        position: relative;
        min-height: 30px;
        padding: 5px;
    }
    .active-element {
        box-shadow: 0px 0px 0px 1px #007bff !important;
    }
    
    #workspace:not(.drag-active) .workspace-component.component-mouseover {
        box-shadow: 0px 0px 0px 1px #4CAF50;
    }
    .workspace-component > .card-header {
        position: absolute;
        bottom: 100%;
        left: 2%;
        width: 96%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        visibility: hidden;
        pointer-events: none;
        margin-bottom: 1px;
    }
    #workspace:not(.drag-active) .workspace-component.active-element > .card-header {
        visibility: visible;
    }
    .workspace-component > .card-header .component-title {
        font-size: 12px;
    }
    .workspace-component > .card-header .btn{
        pointer-events: all;
        font-size: 12px;
        position: relative;
        z-index: 120;
    }
    .workspace-component .btn-toolbar {
        display: flex;
        align-items: center;
    }

    .workspace-component .btn-toolbar .btn {
        margin-right: 5px;
    }
    .workspace-component.ui-draggable-clone{
        display: block;
        width: auto;
    }
    .drop-hover {
        background-color: green;
    }
    .ui-draggable-clone{
        opacity: 0.2;
    }


    .drop-zone{
        width: 100%;
        height: 5px;
        margin-top: -5px;
        transform: translateY(2.5px);
        position:relative;
        z-index: 100;
        pointer-events: none;
        /*background-color: gray;*/
    } 
    .drop-zone::before {
        content: "";
        position: absolute;
        top: calc(50% - 5px);
        left: 0;
        height: 10px;
        width: 2px;
    } 
    .drop-zone::after {
        content: "";
        position: absolute;
        top: calc(50% - 5px);
        right: 0;
        height: 10px;
        width: 2px;
    } 
    .drop-zone .plus-button{
        display: none;
        position: absolute;
        border-radius: 2px;
        color: white;
        height: 15px;
        width: 15px;
        line-height: 15px;
        bottom: -10px;
        right: calc(50% - 7.5px);
    }
    .drop-zone .drop-line{
        position: absolute;
        top: calc(50% - 1.5px);
        left: 0;
        width: 100%;
        height: 2px;
    }
    *:has(+ .workspace-component.active-element:not(.ui-draggable-clone)),
    .active-element .drop-zone{
        visibility: hidden;
    }
    .workspace-component.active-element + .drop-zone {
        pointer-events: all;
        background-color: blue;
    }
    .workspace-component.active-element + .drop-zone .plus-button {
        display: block;
        background-color: blue;
    }
    .workspace-component.active-element + .drop-zone:hover,
    .workspace-component.active-element + .drop-zone:hover .plus-button {
        background: black;
    } 
    .workspace-component.container-no-child > .drop-zone {
        height: 100%;
        top: 0;
        transform: none;
        margin: 0;
    }
    .workspace-component.row > .drop-zone{
        width: 5px;
        margin: 0;
        padding: 0;
        height: initial;
        margin-left: -5px;
        transform: translateX(2.5px);
    }

    .workspace-component.row > .drop-zone::before {
        top: 0;
        left: calc(50% - 5px);
        height: 2px;
        width: 10px;
    }
    .workspace-component.row > .drop-zone::after {
        bottom: 0;
        top: unset;
        left: calc(50% - 5px);
        height: 2px;
        width: 10px;
    }
    .workspace-component.row > .drop-zone > .plus-button{
        top: calc(50% - 7.5px);
        right: -10px;
        left: unset;
    }
    .workspace-component.row > .drop-zone > .drop-line{
        left: calc(50% - 1.5px);
        top: 0;
        width: 2px;
        height: 100%;
    }
    
    .drop-zone.highlight-dropzone::before, 
    .drop-zone.highlight-dropzone::after,
    .drop-zone.highlight-dropzone .drop-line{
        background: red;
    }
    
    .highlight-dropzone-parent {
        box-shadow: 0px 0px 0px 1px #4CAF50;
    }

</style>

