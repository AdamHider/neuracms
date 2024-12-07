import { findComponentById } from './data.js';
import { renderMarkup, renderWorkspace } from './render.js';

export function renderProperties(type, properties, configs) {
    $('#properties-container').empty();

    const config = configs[type].properties;
    for (const [key, value] of Object.entries(config)) {
        const field = $('<div class="mb-3">').append(
            $('<label>').attr('for', key).addClass('form-label').text(value.label),
            $('<input>').attr({
                type: value.type,
                id: key,
                class: 'form-control',
                value: properties[key] || value.default
            })
        );

        $('#properties-container').append(field);
        $(`#${key}`).on('input', function() {
            properties[key] = $(this).val();
            updateComponentProperties(currentElement, key, properties[key], templates);
            $('#json_content').val(JSON.stringify(pageData));
        });
    }
}

function updateComponentProperties(elementId, property, value, templates, context) {
    const component = findComponentById(elementId, context.pageData);
    if (component) {
        component.properties[property] = value;
        const updatedElementHtml = renderMarkup(templates[component.type], component.properties);
        const updatedElement = $(updatedElementHtml).attr('data-id', elementId);
        updatedElement.append(createComponentControls(component, templates, configs, context));
        $(`[data-id="${elementId}"]`).replaceWith(updatedElement);
    }
    $('#json_content').val(JSON.stringify(context.pageData));
}
