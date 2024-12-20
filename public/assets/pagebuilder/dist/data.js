function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
}
function addComponent(component, parent, index = 0) {
    if(component.is_global){
        component = configs[component.code]
    }
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
function replaceComponent(component, parent, index) {
    if (component.is_global) {
        component = configs[component.code];
    }
    const newComponent = JSON.parse(JSON.stringify(component));
    newComponent.id = generateUniqueId();
    newComponent.children = [];
    for (const [key, value] of Object.entries(component.properties)) {
        newComponent.properties[key] = value.default ?? value;
    }
    if (component.children) {
        component.children.forEach((child, childIndex) => {
            if (component.lock && component.lock === 'all') {
                child.lock = component.lock;
            }
            const newChild = addComponent(child, newComponent, childIndex);
            newComponent.children[childIndex] = newChild;
        });
    }

    if (parent) {
        parent.children[index] = newComponent;
    } else {
        pageData[index] = newComponent;
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
function loadComponents(callback) {
    $.ajax({
        url: '/admin/pages/getComponents',
        method: 'POST',
        contentType: 'application/json',
        success: function(response) {
            $('#offcanvasComponents').html(response.data.html);
            initializeDraggableComponents()
            return callback(response.data.components)
        },
        error: function() {
            console.error(`Failed to load content for components`);
        }
    });
}
function saveComponent(data, component, callback){
    return $.ajax({
        url: '/admin/components/store',
        type: 'POST',
        data: JSON.stringify(data),
        dataType: 'json',
        contentType: false,
        processData: false,
        success: function(response) {
            callback(true, response)
        },
        error: function(err) {
            callback(false, err)
        },
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
function findChildrenByProperty(components, property) {
    const childrenWithProperty = [];
    function traverse(components) {
        for (const component of components) {
            if (component[property]) {
                childrenWithProperty.push(component);
            }

            if (component.children && component.children.length > 0) {
                traverse(component.children);
            }
        }
    }

    traverse(components);
    return childrenWithProperty;
}


function checkGlobalChanges(componentId){                            
    const globalComponentsChanged = findParentsByProperty(pageData, componentId, 'is_global')
    if(globalComponentsChanged.length > 0){
        globalComponentsChanged.forEach((component) => {
            //replaceComponentById()
            updateGlobalComponents(pageData, component.code, component)
            //renderElement(component.id, pageData);
        })
        renderWorkspace()
    }
}
function updateGlobalComponents(components, targetCode, updateData) {
    function traverse(components) {
        for (const component of components) {
            if (component.is_global && component.code === targetCode) {
                replaceComponentById(updateData, component.id, components)
            }

            if (component.children && component.children.length > 0) {
                traverse(component.children);
            }
        }
    }
    traverse(components);
    return components;
}
function findParentsByProperty(components, targetId, property) {
    const parents = [];
    function traverse(components, parentChain) {
        for (const component of components) {
            const newParentChain = [...parentChain, component];

            if (component.id === targetId) {
                // Найден целевой компонент, добавляем всех родителей с указанным свойством в массив родителей
                parents.push(...newParentChain.filter(parent => parent[property]));
            }

            if (component.children && component.children.length > 0) {
                traverse(component.children, newParentChain);
            }
        }
    }
    traverse(components, []);
    return parents;
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
