function openProperties(component) {
    updateSidebarTitle(component.properties.title || component.type);
    renderProperties(component);
}

function updateSidebarTitle(title) {
    $('#sidebar-title').text(title);
}

function renderProperties(component) {
    $('#properties-container').empty();
    if(!configs[component.code]) return;
    const config = configs[component.code].properties;
    for (const [key, value] of Object.entries(config)){
        const field = createField(key, value, component.properties[key]);
        makeInputable($(field).find('[data-key]'), component);
        $('#properties-container').append(field);
    }
}

function makeInputable(field, component){
    $(field).on('input change', function(e) {
        e.preventDefault()
        e.stopPropagation()
        const value = $(this).attr('type') === 'checkbox' ? $(this).is(':checked') :
                      $(this).attr('type') === 'color' ? $(this).val() : $(this).val();

        const key = $(this).data('key');
        component.properties[key] = value;
        if (component.controller) {
            loadDynamicContent(component, $(`[data-id="${component.id}"]`));
        } else {
            updateComponentProperty(component, key, value);
        }
        $('#json_content').val(JSON.stringify(pageData));
    });
}

function updateComponentProperty(component, key, value) {
    if(key == 'title') updateSidebarTitle(value || component.type);
    const updatedElement = createComponent(component, templates, configs);
    
    const targetElement = $(`[data-id="${component.id}"]`);
    targetElement.replaceWith(updatedElement);
    highlightActive(component.id);
}
