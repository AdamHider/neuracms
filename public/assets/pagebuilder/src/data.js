export function generateUniqueId() {
    return 'id-' + Math.random().toString(36).substr(2, 16);
}

export function addComponent(type, parent, configs) {
    const newComponent = {
        id: generateUniqueId(),
        type: type,
        properties: {},
        children: []
    };
    for (const [key, value] of Object.entries(configs[type].properties)) {
        newComponent.properties[key] = value.default;
    }
    parent.push(newComponent);
}

export function addChildComponent(parentId, childType, pageData, configs) {
    const parentComponent = findComponentById(parentId, pageData);
    if (parentComponent) {
        addComponent(childType, parentComponent.children, configs);
    } else {
        console.error(`Parent component with id ${parentId} not found.`);
    }
}

export function findComponentById(id, parent) {
    for (const component of parent) {
        if (component.id === id) {
            return component;
        }
        const found = findComponentById(id, component.children);
        if (found) return found;
    }
    return null;
}

export function removeComponent(id, pageData) {
    const parentComponent = findParentComponentById(id, pageData);
    if (parentComponent) {
        parentComponent.children = parentComponent.children.filter(child => child.id !== id);
    } else {
        pageData = pageData.filter(component => component.id !== id);
    }
}

export function findParentComponentById(id, parent) {
    for (const component of parent) {
        if (component.children.some(child => child.id === id)) {
            return component;
        }
        const found = findParentComponentById(id, component.children);
        if (found) return found;
    }
    return null;
}
