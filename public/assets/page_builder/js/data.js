function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
}

function addComponent(type, parent = pageData) {
    const config = componentConfig[type];
    const newComponent = {
        id: generateUniqueId(), // Присваиваем уникальный идентификатор
        type: type,
        class: '',
        properties: {},
        children: []
    };

    for (const [key, value] of Object.entries(config.properties)) {
        newComponent.properties[key] = value.default;
    }

    parent.push(newComponent);
}

function addChildComponent(parentId, childType) {
    const parentComponent = findComponentById(parentId);
    if (parentComponent) {
        addComponent(childType, parentComponent.children);
    } else {
        console.error(`Parent component with id ${parentId} not found.`);
    }
}

function findComponentById(id, parent = pageData) {
    for (const component of parent) {
        if (component.id === id) {
            return component;
        }
        const found = findComponentById(id, component.children);
        if (found) {
            return found;
        }
    }
    return null;
}

function removeComponent(id) {
    const parentComponent = findParentComponentById(id);
    if (parentComponent) {
        const index = parentComponent.children.findIndex(child => child.id === id);
        parentComponent.children.splice(index, 1);
    } else {
        const index = pageData.findIndex(component => component.id === id);
        pageData.splice(index, 1);
    }
}

function findParentComponentById(id, parent = pageData) {
    for (const component of parent) {
        if (component.children.some(child => child.id === id)) {
            return component;
        }
        const found = findParentComponentById(id, component.children);
        if (found) {
            return found;
        }
    }
    return null;
}
