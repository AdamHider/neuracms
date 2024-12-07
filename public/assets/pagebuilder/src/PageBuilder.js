import * as data from './data.js';
import * as render from './render.js';
import * as properties from './properties.js';

class PageBuilder {
    constructor(workspaceId, pageData = []) {
        this.workspaceId = workspaceId;
        this.pageData = pageData;
        this.templates = {};
        this.configs = {};
        this.currentElement = null;
    }

    async loadComponents(componentTypes) {
        const loadPromises = componentTypes.map(type => this.loadComponent(type));
        await Promise.all(loadPromises);
        this.initWorkspace();
    }

    async loadComponent(type) {
        const templatePromise = $.get(`components/${type}/template.html`, data => {
            this.templates[type] = data;
        });
        const configPromise = $.getJSON(`components/${type}/config.json`, data => {
            this.configs[type] = data;
        });
        await Promise.all([templatePromise, configPromise]);
    }

    initWorkspace() {
        render.renderWorkspace(this.pageData, this.templates, this.configs, this);
        $(document).on('click', '.add-control-section', event => {
            event.preventDefault();
            data.addComponent('container', this.pageData, this.configs);
            render.renderWorkspace(this.pageData, this.templates, this.configs, this);
        });
    }
}

// Экспортируем класс PageBuilder
export default PageBuilder;
