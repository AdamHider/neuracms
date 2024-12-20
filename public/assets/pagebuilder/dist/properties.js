function openProperties(component) {
    renderProperties(component);
    updateSidebarTitle(component.properties.title.value || component.type);
    renderFavouritesTrigger(component)
}
function renderFavouritesTrigger(component){
    component = JSON.parse(JSON.stringify(component)) 
    component.properties = configs[component.code].properties;
    $('#addToFavourites').removeClass('invisible')
    const form = $('#addToFavouritesModal');
    $(form).find('[name="new-template-json"]').val(JSON.stringify(component));
    
    $(form).find('button[type="submit"]').off('click')
    $(form).find('button[type="submit"]').on('click', async (e) => {
        let json_content = JSON.parse($(form).find('[name="new-template-json"]').val())
        json_content.is_global = $(form).find('[name="new-template-is-global"]').prop('checked');
        e.preventDefault()
        const data = {
            name: $(form).find('[name="new-template-name"]').val(),
            group: $(form).find('[name="new-template-group"]').val(),
            json_content: JSON.stringify(json_content)
        }
        saveComponent(data, component, (success, response) => {
            if(success){
                loadComponents((componentGroups) => {
                    const componentOld = findComponentById(component.id, pageData);
                    componentOld.code = response.data.code
                    componentOld.name = response.data.name
                    componentOld.properties.title.value = response.data.name
                    componentOld.type = response.data.type
                    componentOld.group = response.data.group
                    componentOld.is_global = response.data.is_global
                    composeComponents(componentGroups)
                    renderWorkspace(pageData);
                })
                $('#addToFavouritesModal .alert').addClass('invisible')
                $('#addToFavouritesModal').modal('hide');
            } else {
                $('#addToFavouritesModal .alert').removeClass('invisible').html(response.responseJSON.message)
            }
        })

    })
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
            if(!component.properties[key]) component.properties[key] = configs[component.code].properties
            const field = createField(key, value, component.properties[key]?.value);
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
        component.properties[key].value = value;
        if (component.controller) {
            loadDynamicContent(component, $(`[data-id="${component.id}"]`));
        } else {
            updateComponentProperty(component, key, value);
        }
        if(component.is_global){
            const data = {
                name: component.code,
                group: component.group,
                json_content: JSON.stringify(component),
                update: true
            }
            saveComponent(data, component, (success, result) => {
                if(success){
                    loadComponents((componentGroups) => {
                        composeComponents(componentGroups)
                    })
                } 
            })
        }
        $('#json_content').val(JSON.stringify(pageData)).trigger('change');
    });
}

function updateComponentProperty(component, key, value) {
    if(key == 'title') updateSidebarTitle(value || component.type);
    if((component.type == 'container' || component.type == 'template') && component.children.length > 0) {
        renderElement(component.id, pageData, true); // Render self without children
    } else {
        renderElement(component.id, pageData); // Render self with children
    }
    highlightActive(component.id);
}
