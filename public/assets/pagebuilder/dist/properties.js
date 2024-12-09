
function openProperties(component){
    $('.active-element').removeClass('active-element');
    $(`[data-id="${component.id}"]`).addClass('active-element');
    updateSidebarTitle(component.properties.title || component.type);
    renderProperties(component);
}

function updateSidebarTitle(title) {
    $('#sidebar-title').text(title);
}

function renderProperties(component) {
    $('#properties-container').empty();

    var properties = component.properties

    if(!configs[component.code]) return
    const config = configs[component.code].properties;

    for (const [key, value] of Object.entries(config)){
        const field = createField(key, value, properties[key])
        $('#properties-container').append(field);
        $(`#component_${key}`).on('input', function() {
            properties[key] = $(this).val();
            updateSidebarTitle(properties.title || component.type);
            if (component.controller) {
                const element = $(`[data-id="${component.id}"]`);
                loadDynamicContent(component, element);
            } else {
                updateComponentProperty(component.id, key, properties[key]);
            }
            $('#json_content').val(JSON.stringify(pageData));
        });
    }
}

function updateComponentProperty(elementId, key, value) {
    const component = findComponentById(elementId, pageData);
    if (component) {
        component.properties[key] = value;
        const updatedElement = createComponent(component, templates, configs)
        $(`[data-id="${elementId}"]`).replaceWith(updatedElement);

        // Подсвечиваем активный элемент TO DO
        $(`[data-id="${elementId}"]`).addClass('active-element');
    }
}