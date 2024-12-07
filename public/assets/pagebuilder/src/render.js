import { addComponent, addChildComponent, removeComponent } from './data.js';
import { renderProperties } from './properties.js';

export function renderMarkup(template, properties) {
    let renderedTemplate = template;
    for (const [key, value] of Object.entries(properties)) {
        renderedTemplate = renderedTemplate.replace(new RegExp(`{{${key}}}`, 'g'), value);
    }
    return renderedTemplate;
}

export function renderWorkspace(data, templates, configs, context) {
    $(`#${context.workspaceId}`).empty();
    data.forEach(component => {
        const componentElement = createComponent(component, templates, configs, context);
        if (componentElement) $(`#${context.workspaceId}`).append(componentElement);
    });

    const addSectionButton = $('<div class="add-control-section">+ Add Section</div>').on('click', function(event) {
        event.preventDefault();
        addComponent('container', data, configs);
        renderWorkspace(data, templates, configs, context);
    });

    $(`#${context.workspaceId}`).append(addSectionButton);
    $('#json_content').val(JSON.stringify(data));
}

function createComponent(component, templates, configs, context) {
    if (!templates[component.type]) {
        console.error(`Template for component type ${component.type} not found`);
        return null;
    }

    const elementHtml = renderMarkup(templates[component.type], component.properties);
    const element = $(elementHtml).attr('data-id', component.id);

    element.append(createComponentControls(component, templates, configs, context));
    component.children.forEach(child => {
        const childElement = createComponent(child, templates, configs, context);
        if (childElement) element.append(childElement);
    });

    return element;
}

function createComponentControls(component, templates, configs, context) {
    const controls = $('<div class="element-controls controls-wrapper"></div>')
        .append($('<button class="btn btn-info btn-sm">More</button>').on('click', function(event) {
            event.preventDefault();
            $('.active-element').removeClass('active-element');
            $(this).closest(`[data-id="${component.id}"]`).addClass('active-element');
            context.currentElement = component.id;
            renderProperties(component.type, component.properties, configs);
        }))
        .append($('<button class="btn btn-danger btn-sm">Delete</button>').on('click', function(event) {
            event.preventDefault();
            removeComponent(component.id, context.pageData);
            renderWorkspace(context.pageData, templates, configs, context);
        }));

    if (component.type === 'container') {
        controls.append($('<select class="form-select add-child-type"></select>').html(
            Object.keys(templates).map(type => `<option value="${type}">Add ${type}</option>`).join('')
        ));
        controls.append($('<button class="btn btn-primary btn-sm add-child">Add Child</button>').on('click', function(event) {
            event.preventDefault();
            addChildComponent(component.id, $(this).siblings('.add-child-type').val(), context.pageData, configs);
            renderWorkspace(context.pageData, templates, configs, context);
        }));
    }
    
    return controls;
}
