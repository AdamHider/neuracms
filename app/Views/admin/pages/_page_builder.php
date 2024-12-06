<style>
    #workspace { border: 1px solid #ddd; min-height: 300px; padding: 15px; }
    #workspace .element-controls {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 5px;
    }
    #workspace .element-controls button {
        margin-left: 5px;
    }
    #workspace .section { margin-bottom: 15px; border: 2px solid #007bff; padding: 10px; background-color: #f1f1f1; position: relative; }
    #workspace .row { margin-bottom: 15px; border: 2px dashed #17a2b8; padding: 10px; background-color: #e9ecef; position: relative; }
    #workspace .col { margin-bottom: 15px; border: 2px dotted #28a745; padding: 10px; background-color: #d4edda; position: relative; }
    #workspace .add-control { border: 2px dashed #6c757d; padding: 10px; margin-bottom: 15px; text-align: center; cursor: pointer; color: #6c757d; }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-9">
            <div id="workspace" class="container">
                <!-- Рабочая область для конструктора страниц -->
            </div>
            <input type="hidden" name="json_content" id="json_content">
        </div>
        <div id="sidebar" class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Element Properties</h3>
                </div>
                <div class="card-body" id="properties-container">
                    <!-- Поля свойств будут добавляться здесь -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url('assets/page_builder/js/data.js')?>"></script>
<script src="<?php echo base_url('assets/page_builder/js/render.js')?>"></script>
<script src="<?php echo base_url('assets/page_builder/js/properties.js')?>"></script>
<script>
const componentConfig = {
    container: {
        type: 'container',
        properties: {
            class: {
                "label": "Class Name",
                "type": "text",
                "default": ""
            },
            backgroundColor: {
                label: 'Background Color',
                type: 'color',
                default: '#ffffff'
            },
            padding: {
                label: 'Padding',
                type: 'text',
                default: '10px'
            }
        },
        template: 'components/container/template.html'
    },
    content: {
        type: 'content',
        properties: {
            text: {
                label: 'Text',
                type: 'text',
                default: 'Sample text'
            }
        },
        template: 'components/content/template.html'
    }
};

let pageData = <?= isset($page['json_content']) ? json_encode(json_decode($page['json_content']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
let pageBuilderPath = '<?php echo base_url('assets/page_builder')?>';

    let templates = {};
    let configs = {};

    const loadComponents = [
        loadComponent('container'),
        loadComponent('content')
    ];

    $.when(...loadComponents).then(function() {
        renderWorkspace(pageData, templates);
    });

    $('#element-css-class').on('input', function() {
        updateCssClass(currentElement, $(this).val());
        renderWorkspace(pageData, templates);
    });

    $('#load').on('click', function() {
        const content = $('#input').val();
        pageData = JSON.parse(content);
        renderWorkspace(pageData, templates);
    });
    
    function loadComponent(type) {
        return $.when(
            $.get(`${pageBuilderPath}/components/${type}/template.html`, function(data) {
                templates[type] = data;
            }),
            $.getJSON(`${pageBuilderPath}/components/${type}/config.json`, function(data) {
                configs[type] = data;
            })
        );
    }

</script>

<style>
    .active-element {
        border: 2px solid #007bff !important;
        background-color: #f0f8ff !important;
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
    .container-component{
        border: 2px dashed #6c757d;
        padding: 10px;
    }
</style>

