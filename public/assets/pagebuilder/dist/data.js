function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
}
function addComponent(component, parent, index = 0) {
    const newComponent = {
        id: generateUniqueId(),
        type: component.type,
        code: component.code,
        group: component.group,
        controller: component.controller ?? null,
        method: component.method ?? null,
        properties: { ...component.properties },
        children: []
    };
    for (const [key, value] of Object.entries(component.properties)) {
        newComponent.properties[key] = value.default ?? value;
    }
    if (component.children) {
        component.children.forEach((childTemplate, index) => {
            const newChild = addComponent(childTemplate, component);
            newComponent.children[index] = newChild;
        });
    }
    if (parent) {
        parent.children.splice(index, 0, newComponent);
    } else {
        pageData.splice(index, 0, newComponent);
    }
    return newComponent;
}
function moveComponent(elementId, parent, index = 0) {
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
function cloneComponent(componentId, parent, index = 0) {
    const component = findComponentById(componentId, pageData);
    if (component) {
        const shallowCopy = JSON.parse(JSON.stringify(component));
        shallowCopy.id = generateUniqueId()
        shallowCopy.children = cloneChildren(component.children)
        if (parent) {
            parent.children.splice(index, 0, shallowCopy);
        } else {
            pageData.splice(index, 0, shallowCopy);
        }
    }
}
function cloneChildren(children) {
    return children.map(child => {
        const shallowCopy = JSON.parse(JSON.stringify(child));
        shallowCopy.id = generateUniqueId()
        shallowCopy.children = cloneChildren(child.children)
        return shallowCopy;
    });
}

function loadDynamicContent(component, element) {
    $.ajax({
        url: '/component/getGeneratedContent',
        method: 'POST',
        data: JSON.stringify(component),
        contentType: 'application/json',
        success: function(response) {
            element.html(response.html);
            element.append(createComponentControls(component));
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