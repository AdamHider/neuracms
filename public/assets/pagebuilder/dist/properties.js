function openProperties(component) {
    updateSidebarTitle(component.properties.title || component.type);
    renderProperties(component);
    renderFavouritesTrigger(component)
}
function renderFavouritesTrigger(component){
    component = JSON.parse(JSON.stringify(component)) 
    component.properties = configs[component.code].properties;
    $('#addToFavourites').removeClass('invisible')
    $('#addToFavouritesModal [name="new-template-json"]').val(JSON.stringify(component));
    
    $('#addToFavouritesModal button[type="submit"]').off('click')
    $('#addToFavouritesModal button[type="submit"]').on('click', async (e) => {
        let json_content = JSON.parse($('[name="new-template-json"]').val())
        json_content.is_global = $('[name="new-template-is-global"]').prop('checked');
        e.preventDefault()
        const data = {
            name: $('[name="new-template-name"]').val(),
            group: $('[name="new-template-group"]').val(),
            json_content: JSON.stringify(json_content)
        }
        createComponentFromTemplate(data, component)
    })
}
function createComponentFromTemplate(data, component){
    return $.ajax({
        url: '/admin/components/store',
        type: 'POST',
        data: JSON.stringify(data),
        dataType: 'json',
        contentType: false,
        processData: false,
        success: function(response) {
            loadComponents((componentGroups) => {
                const componentOld = findComponentById(component.id, pageData);
                componentOld.code = response.data.code
                componentOld.name = response.data.name
                componentOld.properties.title = response.data.name
                componentOld.type = response.data.type
                componentOld.group = response.data.group
                componentOld.is_global = response.data.is_global
                composeComponents(componentGroups)
                renderWorkspace(pageData);
            })
            $('#addToFavouritesModal .alert').addClass('invisible')
            $('#addToFavouritesModal').modal('hide');
        },
        error: function(err) {
            $('#addToFavouritesModal .alert').removeClass('invisible').html(err.responseJSON.message)
        },
    });
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
    if(component.type == 'container' && component.children.length > 0) {
        renderElement(component.id, pageData, true); // Render self without children
    } else {
        renderElement(component.id, pageData); // Render self with children
    }
    $('#json_content').trigger('change');
    highlightActive(component.id);
}
