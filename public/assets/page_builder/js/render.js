function renderWorkspace(data, templates) {
    $('#workspace').empty();
    data.forEach(component => {
        const componentElement = createComponent(component, templates);
        if (componentElement) {
            $('#workspace').append(componentElement);
        }
    });

    const addSectionButton = $('<div class="add-control-section">+ Add Section</div>').on('click', function(event) {
        event.preventDefault();
        addComponent('container');
        renderWorkspace(pageData, templates);
    });

    $('#workspace').append(addSectionButton);
    $('#json_content').val(JSON.stringify(data));
}

function createComponent(component, templates) {
    if (!templates[component.type]) {
        console.error(`Template for component type ${component.type} not found`);
        return null;
    }

    let elementHtml = templates[component.type];
    for (const [key, value] of Object.entries(component.properties)) {
        elementHtml = elementHtml.replace(`{{${key}}}`, value);
    }

    const element = $(elementHtml).addClass(component.properties.class).attr('data-id', component.id);

    // Удаление существующих элементов управления
    element.find('.element-controls').remove();

    const controls = createComponentControls(component);
    element.append(controls);

    component.children.forEach(child => {
        const childElement = createComponent(child, templates);
        if (childElement) {
            element.append(childElement);
        }
    });

    return element;
}


function createComponentControls(component) {
    const controls = $('<div class="element-controls controls-wrapper"></div>');
    const moreButton = $('<button class="btn btn-info btn-sm">More</button>');
    const deleteButton = $('<button class="btn btn-danger btn-sm">Delete</button>');

    moreButton.on('click', function(event) {
        event.preventDefault();
        $('.active-element').removeClass('active-element');
        $(this).closest(`[data-id="${component.id}"]`).addClass('active-element');
        currentElement = component.id;
        renderProperties(component.type, getCurrentComponentProperties(currentElement));
    });

    deleteButton.on('click', function(event) {
        event.preventDefault();
        removeComponent(component.id);
        renderWorkspace(pageData, templates);
    });

    if (componentConfig[component.type].type === 'container') {
        controls.append($('<select class="form-select add-child-type"></select>').html(
            Object.keys(componentConfig).map(type => `<option value="${type}">Add ${type}</option>`).join('')
        ));

        const addChildButton = $('<button class="btn btn-primary btn-sm add-child">Add Child</button>').on('click', function(event) {
            event.preventDefault();
            const selectedType = $(this).siblings('.add-child-type').val();
            addChildComponent(component.id, selectedType);
            renderWorkspace(pageData, templates);
        });

        controls.append(addChildButton);
    }

    controls.append(moreButton, deleteButton);
    return controls;
}
