function renderWorkspace(data) {
    $('#workspace').empty();
    data.forEach((component, index) => {
        $('#workspace').append(createDropzone('root', index));
        const componentElement = createComponent(component);
        if (componentElement) $('#workspace').append(componentElement);
    });
    $('#workspace').append(createDropzone('root', data.length));
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
    element.append(createComponentControls(component));

    if (component.type === 'container' || component.type === 'template') {
        element.append(createDropzone(component.id, 0));
        if(component.children.length == 0){
            element.addClass('container-no-child');
        }
    }
    
    if (component.controller) {
        loadDynamicContent(component, element)
    } else {
        component.children.forEach((child, childIndex) => {
            const childElement = createComponent(child);
            if (childElement) element.append(childElement).append(createDropzone(child.id, childIndex+1));
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
    const controls = $('<div class="card-header d-flex align-items-end" role="toolbar"></div>')
        .append($(`<span class="d-flex flex-grow-1"><span class="component-title px-2 py-1 rounded-top bg-primary text-white">${component.properties.title || component.type}</span></span>`))
        .append($('<span class="btn btn-secondary btn-sm move-handle rounded-0 rounded-top ms-1"><i class="bi bi-arrows-move"></i></span>'))
        .append($('<span class="btn btn-danger btn-sm rounded-0 rounded-top ms-1"><i class="bi bi-trash"></i></span>').on('click', function(event) {
            removeComponent(component.id, pageData);
            renderWorkspace(pageData);
        }));
    return controls;
}

function createDropzone(elementId, index){
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
    makeDroppable(dropZone, elementId);
    return dropZone;
}