function renderProperties(type, properties) {
    $('#properties-container').empty();

    const config = componentConfig[type].properties;

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
            updateComponentStyle(currentElement, key, properties[key]);
            $('#json_content').val(JSON.stringify(pageData));
        });
    }
}

function updateComponentStyle(elementId, property, value) {
    const component = findComponentById(elementId);
    if (component) {
        component.properties[property] = value;

        if (property === 'class') {
            $(`[data-id="${elementId}"]`).attr('class', value);
        } else {
            $(`[data-id="${elementId}"]`).css(property, value);
        }
    }

    $('#json_content').val(JSON.stringify(pageData));
}


function getCurrentComponentProperties(elementId) {
    const component = findComponentById(elementId);
    return component ? component.properties : {};
}
