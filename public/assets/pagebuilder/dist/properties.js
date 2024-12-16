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
    const groups = {};

    // Группировка свойств по их группам
    for (const [key, value] of Object.entries(config)) {
        const group = value.group || 'General';
        if (!groups[group]) groups[group] = [];
        groups[group].push({ key, value });
    }

    // Отображение свойств по группам
    for (const [group, properties] of Object.entries(groups)) {
        const groupContainer = $('<div class="group-container p-3 border-bottom">').append(
            $('<h6>').addClass('group-title').html(`<span>${group}</span>`)
        );
        const row = $('<div class="row g-2">');
        properties.forEach(({ key, value }) => {
            const field = createField(key, value, component.properties[key]);
            makeInputable($(field).find('[data-key]'), component);
            row.append(field);
        });
        groupContainer.append(row)
        $('#properties-container').append(groupContainer);
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
        $('#json_content').val(JSON.stringify(pageData)).trigger('change');
    });
}

function updateComponentProperty(component, key, value) {
    if(key == 'title') updateSidebarTitle(value || component.type);
    renderElement(component.id, pageData, true); // Render self without children
    highlightActive(component.id);
}
