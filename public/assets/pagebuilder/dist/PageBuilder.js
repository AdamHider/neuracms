//$(document).ready(function() {
    let currentElement = null;
    let templates = {}
    let configs = {}
    function composeComponents(){
        for(const i in components){
            for(const k in components[i].children){
                var component = components[i].children[k]
                templates[component.config.code] = component.template;
                configs[component.config.code] =  component.config;
            }
        }
    }

    function initWorkspace() {
        composeComponents()
        $('.component-item').each(function() {
            addComponentDraggable(this, false)
        });
        renderWorkspace(pageData, templates, configs);
    }

    initWorkspace()

    function renderWorkspace(data) {
        $('#workspace').empty();
        data.forEach((component, index) => {
    
            const dropZoneBefore = $('<div class="drop-zone" data-index="' + (index) + '" data-parent-id="root"></div>');
            addComponentDroppable(dropZoneBefore, 'root');
            $('#workspace').append(dropZoneBefore);

            const componentElement = createComponent(component, templates, configs, index);
            if (componentElement) $('#workspace').append(componentElement);
    
        });
        const dropZoneAfter = $('<div class="drop-zone" data-index="' + (data.length) + '" data-parent-id="root"></div>');
        addComponentDroppable(dropZoneAfter, 'root');
        $('#workspace').append(dropZoneAfter);
        if(data.length == 0){
            const dropZoneAfter = $('<div class="drop-zone" data-index="' + (data.length) + '" data-parent-id="root"></div>');
            addComponentDroppable(dropZoneAfter, 'root');
            $('#workspace').append(dropZoneAfter);
        }
        $('#json_content').val(JSON.stringify(data));

        $('.workspace-component').each(function() {
            addComponentDraggable(this)
            addComponentHoverable(this)
        });
        
    }
    
    function updateComponentOrder() {
        const newOrder = [];
        $('#workspace').children('.workspace-component').each(function() {
            const componentId = $(this).attr('data-id');
            const component = pageData.find(comp => comp.id === componentId);
            if (component) {
                newOrder.push(component);
            }
        });
        pageData = newOrder;
        $('#json_content').val(JSON.stringify(pageData));
    }
    

    function addComponentHoverable(element){
        $(element).on('mouseenter', function(e){
            e.stopPropagation();
            $(this).addClass('component-mouseover');
            $(this).parent().mouseleave();
        }).on('mouseleave', function(e){
            e.preventDefault();
            $(this).removeClass('component-mouseover');
            $(this).parent().mouseenter();
        });
    }
    function addComponentDraggable(element, handle = '.move-handle'){
        $(element).draggable({
            handle: handle,
            revert: true,
            helper: 'clone',
            scrollSpeed: 100,
            zIndex: 1000,
            cursor: "crosshair",
            /*
            containment: "form",*/
            revertDuration: 100,
            start: function(event, ui) {
                $('#workspace').addClass('drag-active');
                $(ui.helper).addClass("ui-draggable-clone");
                $(this).css('opacity', '0.5');
            },
            stop: function(event, ui) {
                $('#workspace').removeClass('drag-active');
                $(ui.helper).removeClass("ui-draggable-clone");
                $(this).css('opacity', '1');
            }
        });
    }
    function addComponentDroppable(element) {
        $(element).droppable({
            tolerance: "pointer",
            greedy: true,
            accept: '.component-item, .workspace-component',
            drop: function(event, ui) {
                const dropZoneIndex = $(this).data('index');
                $('.highlight-dropzone').removeClass('highlight-dropzone');
                $('.highlight-dropzone-parent').removeClass('highlight-dropzone-parent');
                const code = ui.helper.data('code');
                const type = ui.helper.data('type');
                
                if (code) {
                    const parentId = $(this).closest('.container-component').data('id');
                    const parentComponent = findComponentById(parentId, pageData);
                    if(type === 'template') {
                        addTemplateToWorkspace(configs[code], parentComponent, dropZoneIndex);
                    } else {
                        addComponentToWorkspace(configs[code], parentComponent, dropZoneIndex);
                    }
                } else {
                    const elementId = ui.helper.data('id');
                    const parentId = $(this).closest('.container-component').data('id');
                    const parentComponent = findComponentById(parentId, pageData);
                    moveComponentToWorkspace(elementId, parentComponent, dropZoneIndex);
                }
                renderWorkspace(pageData);
            },
            over: function(event, ui) {
                $(this).addClass('highlight-dropzone');
                $(this).closest('.workspace-component').addClass('highlight-dropzone-parent');
            },
            out: function(event, ui) {
                $(this).removeClass('highlight-dropzone');
                $(this).closest('.workspace-component').removeClass('highlight-dropzone-parent');
            }
        });
    }
    

    function addTemplateToWorkspace(template, parent, index = 0) {
        // Создание нового объекта для добавления в pageData на основе шаблона
        const newComponent = {
            id: generateUniqueId(),
            type: template.type,
            code: template.code,
            group: template.group,
            properties: { ...template.properties },
            children: []
        };
        if(template.type == 'template'){
            for (const [key, value] of Object.entries(template.properties)) {
                newComponent.properties[key] = value.default;
            }
        }
        // Рекурсивное создание и добавление дочерних элементов
        if (template.children) {
            template.children.forEach((childTemplate, index) => {
                const newChild = addTemplateToWorkspace(childTemplate, newComponent);
                newComponent.children[index] = newChild;
            });
        }
        if (parent) {
            parent.children.push(newComponent);
        } else {
            pageData.push(newComponent);
        }

        return newComponent;
    }
    
    function addComponentToWorkspace(component, parent, index = 0) {
        const newComponent = {
            id: generateUniqueId(),
            type: component.type,
            code: component.code,
            group: component.group,
            controller: component.controller ?? null,
            method: component.method ?? null,
            properties: {},
            children: []
        };
        for (const [key, value] of Object.entries(component.properties)) {
            newComponent.properties[key] = value.default;
        }
        if (parent) {
            parent.children.splice(index, 0, newComponent);
        } else {
            pageData.splice(index, 0, newComponent);
        }
    }

    function moveComponentToWorkspace(elementId, parent, index = 0) {
        const component = findComponentById(elementId, pageData);
        if (component) {
            if (parent) {
                removeComponent(elementId, pageData);
                component.id = generateUniqueId();
                parent.children.splice(index, 0, component);
            } else {
                removeComponent(elementId, pageData);
                pageData.splice(index, 0, component);
            }
        }
    }

    function createComponent(component, templates, configs, index = 0) {
        let elementHtml;
        if (!templates[component.code]) {
            console.warn(`Template for component type ${component.type} not found. Creating an empty placeholder component.`);
            elementHtml = '<div class="empty-component">Component template not found. This is a placeholder.</div>';
        } else {
            elementHtml = renderMarkup(templates[component.code], component.properties);
        }
        const element = $(elementHtml).attr('data-id', component.id).addClass('workspace-component');

        element.append(createComponentControls(component, templates, configs));
        // Добавляем класс для контейнеров
        if (component.type === 'container' || component.type === 'template') {
            element.addClass('container-component');
            if(component.children.length == 0){
                element.addClass('container-no-child');
            } else { 
                element.removeClass('container-no-child');
            }
            const dropZoneInitial = $('<div class="drop-zone" data-index="0"></div>');
            addComponentDroppable(dropZoneInitial, component.id);
            element.append(dropZoneInitial);
        } 
        $(element).on('click', (event) => {
            event.stopPropagation();
            openProperties(component, templates, configs)
        })
        if (component.controller) {
            loadDynamicContent(component, element, templates, configs)
        } else {
            component.children.forEach((child, childIndex) => {
                const childElement = createComponent(child, templates, configs, childIndex);
                if (childElement) element.append(childElement);
                const dropZoneAfter = $('<div class="drop-zone" data-index="' + (childIndex+1) + '" ></div>');
                addComponentDroppable(dropZoneAfter, child.id);
                dropZoneAfter.insertAfter(childElement);
            });
        }
        return element;
    }
    
    function loadDynamicContent(component, element, templates, configs) {
        $.ajax({
            url: '/component/getGeneratedContent', // Путь к вашему серверному обработчику
            method: 'POST',
            data: JSON.stringify(component),
            contentType: 'application/json',
            success: function(response) {
                element.html(response.html);
                element.append(createComponentControls(component, templates, configs));
                component.children.forEach(child => {
                    const childElement = createComponent(child, templates, configs);
                    if (childElement) element.append(childElement);
                });
            },
            error: function() {
                console.error(`Failed to load dynamic content for component ${component.id}`);
            }
        });
    }

    function renderMarkup(template, properties) {
        let renderedTemplate = template;
        for (const [key, value] of Object.entries(properties)) {
            renderedTemplate = renderedTemplate.replace(new RegExp(`{{${key}}}`, 'g'), value);
            renderedTemplate = renderedTemplate.replace(new RegExp(`{{children}}`, 'g'), '');
        }
        return renderedTemplate;
    }
    
    function createComponentControls(component, templates, configs) {
        const controls = $('<div class="card-header d-flex align-items-end" role="toolbar"></div>')
            .append($(`<span class="d-flex flex-grow-1"><span class="component-title px-2 py-1 rounded-top bg-primary text-white">${component.properties.title || component.type}</span></span>`))
            .append($('<span class="btn btn-secondary btn-sm move-handle rounded-0 rounded-top ms-1"><i class="bi bi-arrows-move"></i></span>'))
            .append($('<span class="btn btn-danger btn-sm rounded-0 rounded-top ms-1"><i class="bi bi-trash"></i></span>').on('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                removeComponent(component.id, pageData);
                renderWorkspace(pageData);
            }));
        return controls;
    }

    function openProperties(component, templates, configs){
        $('.active-element').removeClass('active-element');
        const $element = $(`[data-id="${component.id}"]`);
        $element.addClass('active-element');
        currentElement = component.id;
        updateSidebarTitle(component.properties.title || component.type);
        renderProperties(component, configs);
    }

    function findComponentById(id, parent) {
        for (const component of parent) {
            if (component.id === id) {
                return component;
            }
            const found = findComponentById(id, component.children);
            if (found) return found;
        }
        return null;
    }

    function generateUniqueId() {
        return 'id-' + Math.random().toString(36).substr(2, 16);
    }

    function updateSidebarTitle(title) {
        $('#sidebar-title').text(title);
    }

    function renderProperties(component, configs) {
        $('#properties-container').empty();
        var code = component.code
        var properties = component.properties
        if(!configs[code]) return
        const config = configs[code].properties;
        for (const [key, value] of Object.entries(config)) 
        {
            var input = null
            if(value.type == 'text.input'){
                input = $('<input>').attr({
                    code: value.code,
                    id: `component_${key}`,
                    class: 'form-control',
                    value: properties[key] || value.default
                })
            } else if(value.type == 'number.input'){
                input = $('<input>').attr({
                    code: value.code,
                    id: `component_${key}`,
                    class: 'form-control',
                    value: properties[key] || value.default
                })
            } else if (value.type == 'text.textarea'){
                input =  $('<textarea>').attr({
                    code: value.code,
                    id: `component_${key}`,
                    class: 'form-control',
                }).html(properties[key] || value.default)
            } 
            

            const field = $('<div class="mb-3">').append(
                $('<label>').attr('for', key).addClass('form-label').text(value.label),
                $(input)
            );

            $('#properties-container').append(field);
            $(`#component_${key}`).on('input', function() {
                properties[key] = $(this).val();
                if (component.controller) {
                    loadDynamicContent(component, $(`[data-id="${component.id}"]`), templates, configs);
                } else {
                    updateComponentProperties(currentElement, key, properties[key], templates, pageData, configs);
                }
                $('#json_content').val(JSON.stringify(pageData));
            });
        }
    }

    function updateComponentProperties(elementId, property, value, templates, pageData, configs) {
        const component = findComponentById(elementId, pageData);
        if (component) {
            component.properties[property] = value;
            const updatedElementHtml = renderMarkup(templates[component.code], component.properties);

            updateSidebarTitle(component.properties.title || component.type);

            const updatedElement = $(updatedElementHtml).attr('data-id', elementId).addClass('workspace-component ui-draggable');
            updatedElement.append(createComponentControls(component, templates, configs));
            if (component.type === 'container') {
                const children = $(`[data-id="${elementId}"]`).children(':not(.card-header)');
                updatedElement.append(children).addClass('container-component');
                addComponentDroppable(updatedElement)
            }

            addComponentDraggable(updatedElement)
            addComponentHoverable(updatedElement)
            $(updatedElement).on('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openProperties(component, templates, configs)
            })
            $(`[data-id="${elementId}"]`).replaceWith(updatedElement);

            // Подсвечиваем активный элемент
            if (currentElement === elementId) {
                $(`[data-id="${elementId}"]`).addClass('active-element');
            }
        }
        $('#json_content').val(JSON.stringify(pageData));
    }

    function removeComponent(id, parent = pageData) {
        $('#properties-container').empty();
        componentIndex = parent.findIndex(component => component.id === id);
        
        if (componentIndex !== -1) {
            parent.splice(componentIndex, 1);
            return true;
        }

        for (const component of parent) {
            if (component.children.length > 0) {
                const removed = removeComponent(id, component.children);
                if (removed) return true;
            }
        }

        return false;
    }

//});

