function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
}
function addComponent(component, parent, index = 0) {
    const newComponent = JSON.parse(JSON.stringify(component));
    newComponent.id = generateUniqueId()
    newComponent.children = []

    for (const [key, value] of Object.entries(component.properties)) {
        newComponent.properties[key] = value.default ?? value;
    }
    if (component.children) {
        component.children.forEach((child, index) => {
            if(component.lock && component.lock == 'all'){
                child.lock = component.lock
            }
            const newChild = addComponent(child, newComponent, index);
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
    return component
}
function cloneComponent(componentId, parent, index = 0) {
    const component = findComponentById(componentId, pageData);
    if (component) {
        const copy = JSON.parse(JSON.stringify(component));
        copy.id = generateUniqueId()
        copy.children = cloneChildren(component.children)
        if (parent) {
            parent.children.splice(index, 0, copy);
        } else {
            pageData.splice(index, 0, copy);
        }
    }
}
function cloneChildren(children) {
    return children.map(child => {
        const copy = JSON.parse(JSON.stringify(child));
        copy.id = generateUniqueId()
        copy.children = cloneChildren(child.children)
        return copy;
    });
}

function loadDynamicContent(component, element) {
    if(element.children().length > 0){
        element.css('width', element.width())
        element.css('height', element.height())
    }
    $.ajax({
        url: '/component/getGeneratedContent',
        method: 'POST',
        data: JSON.stringify(component),
        contentType: 'application/json',
        success: function(response) {
            element.css('width', 'initial')
            element.css('height', 'initial')
            element.html(response.html);
            element.append(createControls(component));
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
function findParentComponent(childId, data) {
    for (const component of data) {
        if (component.children) {
            for (const child of component.children) {
                if (child.id === childId) {
                    return component;
                }
            }
            const foundParent = findParentComponent(childId, component.children);
            if (foundParent) return foundParent;
        }
    }
    return null;
}
function getComponentAccept(component){
    let accept = '.component-item, .workspace-component';
    if(!component.accept){
        return accept;
    }
    if(component.accept == 'none'){
        return false
    }
    if(component.accept == 'all'){
        return accept
    }
    accept = 'item';
    for(const type of component.accept.split(',')){
        accept += `, .${type}-component`
    }
    return accept
}
