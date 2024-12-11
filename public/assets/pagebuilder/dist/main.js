
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
    initializeDraggableComponents()
    renderWorkspace(pageData);
}
function initializeDraggableComponents() {
    $('.component-item').each(function() {
        makeDraggable(this, false);
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
    if($(element).hasClass('locked-all-component') || $(element).hasClass('locked-self-component')) return false
    $(element).draggable({
        handle: handle,
        revert: true,
        helper: 'clone',
        zIndex: 1000,
        revertDuration: 100,
        start: function(event, ui) {
            $('.active-element').removeClass('active-element');
            $('#workspace').addClass('drag-active');
            $(ui.helper).addClass("ui-draggable-clone");
            $(this).next().addClass('hidden');
            $(this).css('opacity', '0.5');
        },
        stop: function(event, ui) {
            $('#workspace').removeClass('drag-active');
            $(ui.helper).removeClass("ui-draggable-clone");
            $('hidden').removeClass('hidden');
            $(this).css('opacity', '1');
        }
    });
}
function makeDroppable(target, accept) {
    if($(target).hasClass('locked-all-component')) return false
    $(target).droppable({
        tolerance: "pointer",
        greedy: true,
        accept: accept,
        drop: function(event, ui) {
            const dropZoneIndex = $(this).data('index');
            resetDropzoneHighlighting();
            const code = ui.helper.data('code');
            const parentId = $(this).closest('.container-component').data('id');
            const parentComponent = findComponentById(parentId, pageData);
            if (code) {
                addComponent(configs[code], parentComponent, dropZoneIndex);
            } else {
                const elementId = ui.helper.data('id');
                moveComponent(elementId, parentComponent, dropZoneIndex);
            }
            renderWorkspace(pageData);
        },
        over: function(event, ui) {
            highlightDropzone(this);
        },
        out: function(event, ui) {
            resetDropzoneHighlighting();
        }
    });
}
function makeClickable(element) {
    const elementId = $(element).data('id')
    const component = findComponentById(elementId, pageData);
    $(element).on('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        resetActiveHighlighting();
        openProperties(component);
        highlightActive(component.id);
    })
}
function isLocked(element){
    
    return false
}

function resetActiveHighlighting(){
    $('.active-element').removeClass('active-element');
}
function highlightActive(elementId){
    $(`[data-id="${elementId}"]`).addClass('active-element');
}

function resetDropzoneHighlighting() {
    $('.highlight-dropzone').removeClass('highlight-dropzone');
    $('.highlight-dropzone-parent').removeClass('highlight-dropzone-parent');
}
function highlightDropzone(element) {
    $(element).addClass('highlight-dropzone');
    $(element).closest('.workspace-component').addClass('highlight-dropzone-parent');
}

initWorkspace()

