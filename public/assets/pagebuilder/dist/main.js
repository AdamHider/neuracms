
let templates = {}
let configs = {}
function composeComponents() {
    components.forEach(componentGroup => {
        componentGroup.children.forEach(component => {
            templates[component.config.code] = component.template;
            configs[component.config.code] = component.config;
        });
    });
}

function initWorkspace() {
    composeComponents()
    renderWorkspace(pageData);
}

function initializeDraggableComponents() {
    $('.component-item, .workspace-component').each(function() {
        makeDraggable(this, false);
    });
}
function initializeHoverableComponents() {
    $('.workspace-component').each(function() {
        makeHoverable(this, false);
    });
}

function makeHoverable(element){
    $(element).on('mouseenter', function(e){
        e.stopPropagation();
        $(this).addClass('component-mouseover').parent().mouseleave();
    }).on('mouseleave', function(e){
        $(this).removeClass('component-mouseover').parent().mouseenter();
    });
}
function makeDraggable(element, handle = '.move-handle'){
    $(element).draggable({
        handle: handle,
        revert: true,
        helper: 'clone',
        scrollSpeed: 100,
        zIndex: 1000,
        cursor: "crosshair",
        revertDuration: 100,
        start: function(event, ui) {
            $('#workspace').addClass('drag-active');
            $(ui.helper).addClass("ui-draggable-clone");
            $(this).css('opacity', '0.5');
        },
        stop: function(event, ui) {
            $('#workspace').removeClass('drag-active');
            $(ui.helper).removeClass("ui-draggable-clone");
            $(this).css('opacity', '1');
        }
    });
}
function makeDroppable(element) {
    $(element).droppable({
        tolerance: "pointer",
        greedy: true,
        accept: '.component-item, .workspace-component',
        drop: function(event, ui) {
            const dropZoneIndex = $(this).data('index');
            resetHighlighting();
            const code = ui.helper.data('code');
            if (code) {
                const parentId = $(this).closest('.container-component').data('id');
                const parentComponent = findComponentById(parentId, pageData);
                const type = ui.helper.data('type');
                if(type === 'template') {
                    addTemplateToWorkspace(configs[code], parentComponent, dropZoneIndex);
                } else {
                    addComponentToWorkspace(configs[code], parentComponent, dropZoneIndex);
                }
            } else {
                const elementId = ui.helper.data('id');
                const parentId = $(this).closest('.container-component').data('id');
                const parentComponent = findComponentById(parentId, pageData);
                moveComponentToWorkspace(elementId, parentComponent, dropZoneIndex);
            }
            renderWorkspace(pageData);
        },
        over: function(event, ui) {
            highlightDropzone($(this));
        },
        out: function(event, ui) {
            resetHighlighting();
        }
    });
}

function resetHighlighting() {
    $('.highlight-dropzone').removeClass('highlight-dropzone');
    $('.highlight-dropzone-parent').removeClass('highlight-dropzone-parent');
}

function highlightDropzone(dropzone) {
    dropzone.addClass('highlight-dropzone');
    dropzone.closest('.workspace-component').addClass('highlight-dropzone-parent');
}

initWorkspace()

