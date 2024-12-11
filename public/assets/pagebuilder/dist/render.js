function renderWorkspace(data) {
    $('#workspace').empty();
    data.forEach((component, index) => {
        $('#workspace').append(createDropzone(index));
        const componentElement = createComponent(component);
        if (componentElement) $('#workspace').append(componentElement);
    });
    $('#workspace').append(createDropzone(data.length));
    $('#json_content').val(JSON.stringify(data));
}


function createComponent(component) {
    let elementHtml;
    if (!templates[component.code]) {
        elementHtml = '<div class="empty-component">Component template not found. This is a placeholder.</div>';
    } else {
        elementHtml = renderMarkup(templates[component.code], component.properties);
    }
    const element = $(elementHtml).attr('data-id', component.id).addClass('workspace-component '+component.type+'-component');
    if(component.lock) {
        element.addClass('locked-component');
    } 
    element.append(createComponentControls(component));
    if (component.type === 'container' || component.type === 'template') {
        element.append(createDropzone(0, component));
        if(component.children.length == 0){
            element.addClass('container-no-child');
        }
    }
    if (component.controller) {
        loadDynamicContent(component, element)
    } else {
        component.children.forEach((child, childIndex) => {
            const childElement = createComponent(child);
            if (childElement) element.append(childElement).append(createDropzone(childIndex+1, component));
        });
    }
    makeHoverable(element)
    makeClickable(element)
    makeDraggable(element);
    return element;
}

function renderMarkup(template, properties) {
    let renderedTemplate = template;
    for (const [key, value] of Object.entries(properties)) {
        renderedTemplate = renderedTemplate.replace(new RegExp(`{{${key}}}`, 'g'), value);
        renderedTemplate = renderedTemplate.replace(new RegExp(`{{children}}`, 'g'), '');
    }
    return renderedTemplate;
}

function createComponentControls(component) {
    const controls = $('<div class="card-header d-flex align-items-center bg-primary" role="toolbar"></div>');
    if(!component.lock){
        controls.append($('<span class="btn btn-sm move-handle rounded-0 rounded-top text-white"><i class="bi bi-grip-vertical"></i></span>'));
    }
    controls.append($(`<span class="component-title move-handle px-2 py-1 rounded-top text-white">${component.properties.title || component.type}</span>`))
    if(!component.lock){
        controls.append($('<span class="btn btn-sm rounded-0 rounded-top text-white"><i class="bi bi-x"></i></span>').on('click', function(event) {
            removeComponent(component.id, pageData);
            renderWorkspace(pageData);
        }));
    }

    return controls;
}

function createDropzone(index, component = {}){
    const accept = getComponentAccept(component)
    if(!accept || component.lock) return ''
    const dropZone = $('<div class="drop-zone" data-index="' + index + '" ><span class="drop-line"></span><span class="plus-button"><i class="bi bi-plus"></i></span></div>');
    $(dropZone).on('click', (event) => {
        event.stopPropagation()
        const activeElementId = $('.active-element').data('id');
        const parentId = $('.active-element').parent().closest('.container-component').data('id');
        const parentComponent = findComponentById(parentId, pageData);
        if(!activeElementId) return
        cloneComponent(activeElementId, parentComponent, index)
        resetActiveHighlighting()
        renderWorkspace(pageData);
    });
    makeDroppable(dropZone, accept);
    return dropZone;
}

function getComponentAccept(component){
    let accept = '.component-item, .workspace-component';
    if(!component.accept){
        return accept;
    }
    if(component.accept == 'none'){
        return false
    }
    if(component.accept == 'all'){
        return accept
    }
    accept = 'item';
    for(const type of component.accept.split(',')){
        accept += `, .${type}-component`
    }
    return accept
}

