function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
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
function loadDynamicContent(component, element) {
    $.ajax({
        url: '/component/getGeneratedContent',
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