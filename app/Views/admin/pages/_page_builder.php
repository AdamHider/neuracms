<div class="container-fluid">
    <div class="row">
        <div class="col-md-9">
            <div id="available-components-container">
                <ul id="available-components">
                    <?php foreach ($components as $component): ?>
                        <li class="component-item " data-type="<?= $component ?>"><?= ucfirst($component) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div id="workspace" class="container">
                <!-- Рабочая область для конструктора страниц -->
            </div>
            <input type="hidden" name="json_content" id="json_content">
        </div>
        <div id="sidebar" class="col-md-3 d-flex flex-column flex-shrink-0">
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
</div>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<script>
const pageData = <?= isset($page['json_content']) ? json_encode(json_decode($page['json_content']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
</script>
<script src="<?php echo base_url('assets/pagebuilder/dist/PageBuilder.js')?>"></script>
<script>
    
</script>

<style>
    #workspace { border: 1px solid #ddd; min-height: 300px; padding: 15px; }
    #workspace .section { margin-bottom: 15px; border: 1px solid #007bff; padding: 10px; background-color: #f1f1f1; position: relative; }
    #workspace .add-control { border: 1px dashed #6c757d; padding: 10px; margin-bottom: 15px; text-align: center; cursor: pointer; color: #6c757d; }

    .content-component, .container-component{
        border: 1px dashed #6c757d;
    }
    .workspace-component {
        margin: 5px;
        position: relative;
    }
    .active-element {
        box-shadow: 0px 0px 0px 1px #007bff !important;
    }
    .add-control-section {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10px;
        border: 2px dashed #6c757d;
        color: #6c757d;
        cursor: pointer;
        margin-top: 10px;
    }
    .add-control-section:hover {
        background-color: #f8f9fa;
        color: #007bff;
    }
    .drop-zone {
        border: 2px dashed #ccc;
        padding: 10px;
        text-align: center;
        margin: 10px 0;
        background-color: #f9f9f9;
        display: none;
    }
    #workspace.drag-active .drop-zone {
        /*display: block;*/
    }

    .highlight-dropzone {
        box-shadow: 0px 0px 0px 1px #4CAF50;
        background-color: #f0fff0;
    }
    .highlight-dropzone-parent {
        box-shadow: 0px 0px 0px 1px #4CAF50;
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
    }
    #workspace:not(.drag-active) .workspace-component.active-element > .card-header {
        visibility: visible;
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

</style>

