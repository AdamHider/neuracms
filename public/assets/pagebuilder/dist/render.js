function renderWorkspace() {
    $('#workspace').empty();
    pageData.forEach((component, index) => {
        const componentElement = createElement(component);
        if (componentElement) $('#workspace').append(componentElement);
    });
    $('#json_content').val(JSON.stringify(pageData));
}

function renderElement(componentId, data, preserveChildren = false, preserveGrandchildren = false) {
    const component = findComponentById(componentId, data);
    if (component) {
        const componentElement = createElement(component, preserveChildren, preserveGrandchildren);
        const targetElement = $(`[data-id="${componentId}"]`);
        targetElement.replaceWith(componentElement);
    }
    $('#json_content').val(JSON.stringify(pageData));
}

function createElement(component, preserveChildren = false, preserveGrandchildren = false) {
    let elementHtml;
    if (!templates[component.code]) {
        elementHtml = '<div class="empty-component">Component template not found. This is a placeholder.</div>';
    } else {
        elementHtml = renderMarkup(templates[component.code], component.properties);
    }

    const element = $(elementHtml).attr('data-id', component.id).addClass('workspace-component '+component.type+'-component');

    if(component.lock)  element.addClass('locked-component');
    if(component.ghost) element.addClass('ghost-component');

    element.append(createControls(component));
    
    if (component.type === 'container' || component.type === 'template') {
        element.append(createDropzone(0, component, element));
        if(component.children.length == 0){
            element.addClass('container-no-child');
        }
    }
    if(preserveChildren){
        const targetElement = $(`[data-id="${component.id}"]`);
        if(targetElement.children().not(".component-controls").length > 0){
            element.empty()
            element.append(targetElement.children())
        }
    } else {
        if (component.controller) {
            loadDynamicContent(component, element)
        } else {
            component.children.forEach((child, childIndex) => {
                let childElement;
                if (preserveGrandchildren) {
                    childElement = createElement(child, true);
                } else {
                    childElement = createElement(child);
                }
                if (childElement) element.append(childElement).append(createDropzone(childIndex+1, component, childElement));
            });
        }
    }
    if(!component.ghost){
        makeHoverable(element)
        makeClickable(element)
        makeDraggable(element);
    }
    return element;
}

function renderMarkup(template, properties) {
    let renderedTemplate = template;
    for (const [key, value] of Object.entries(properties)) {
        renderedTemplate = renderedTemplate.replace(new RegExp(`{{${key}}}`, 'g'), value.value);
        renderedTemplate = renderedTemplate.replace(new RegExp(`{{children}}`, 'g'), '');
    }
    return renderedTemplate;
}

function createControls(component) {
    if(component.ghost) return {}
    const controls = $('<div class="component-controls card-header d-flex align-items-center bg-primary" role="toolbar"></div>');
    if(!component.lock){
        controls.append($('<span class="btn btn-sm move-handle rounded-0 rounded-top text-white"><i class="bi bi-grip-vertical"></i></span>'));
    }
    controls.append($(`<span class="component-title move-handle px-2 py-1 rounded-top text-white">${component.properties.title.value || component.type}</span>`))
    if(!component.lock){
        controls.append($('<span class="btn btn-sm rounded-0 rounded-top text-white"><i class="bi bi-x"></i></span>').on('click', function(event) {
            const parentId = $(this).closest('.workspace-component').parent().data('id')
            removeComponent(component.id, pageData);
            renderElement(parentId, pageData, false, true); // Render siblings without children
            $('#json_content').trigger('change');
        }));
    }

    return controls;
}

function createDropzone(index, component = {}, element = {}){
    const accept = getComponentAccept(component)
    if(!accept) return ''
    const dropZone = $('<div class="drop-zone" data-index="' + index + '" ><span class="drop-line"></span><span class="plus-button"><i class="bi bi-plus"></i></span></div>');
    $(dropZone).on('click', (event) => {
        event.stopPropagation()
        const activeElementId = $('.active-element').data('id');
        if(!activeElementId) return
        const parentId = $('.active-element').parent().closest('.container-component').data('id');
        const parentComponent = findComponentById(parentId, pageData);
        cloneComponent(activeElementId, parentComponent, index)
        resetActiveHighlighting()
        renderElement(parentId, pageData, false, true); // Render siblings without children
        $('#json_content').trigger('change');
    });
    makeDroppable(dropZone, accept);
    return dropZone;
}

