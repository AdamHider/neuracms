/*
 * Gantry-style page builder.
 *
 * The layout is a list of sections; each section holds columns; each column
 * holds blocks ("particles"). This script renders that model, lets you drag
 * particles from the library into columns, reorder and move them, drag whole
 * sections, and edit settings. The JSON textarea stays in sync and is what the
 * server actually reads, so the editor degrades to raw JSON editing without JS.
 */
(function () {
    'use strict';

    var root = document.getElementById('builder');
    if (!root) {
        return;
    }

    var form = document.getElementById('builder-form');
    var canvas = document.getElementById('builder-canvas');
    var libraryEl = document.getElementById('builder-library');
    var settingsBody = document.getElementById('builder-settings-body');
    var jsonWrap = document.getElementById('builder-json-wrap');
    var jsonArea = document.getElementById('builder-json');
    var countEl = document.getElementById('builder-count');
    var searchEl = document.getElementById('builder-search');
    var presetsEl = document.getElementById('builder-presets');
    var globalsEl = document.getElementById('builder-globals');

    var config = { layout: { sections: [] }, types: [], categories: {}, presets: [], globals: {}, columns: 12 };
    try {
        config = JSON.parse(root.getAttribute('data-builder')) || config;
    } catch (e) {
        config = config;
    }

    var types = {};
    // The payload may send types as a list or as a name-keyed object.
    var typeList = config.types || [];
    if (Array.isArray(typeList)) {
        typeList.forEach(function (t) { types[t.name] = t; });
    } else {
        Object.keys(typeList).forEach(function (key) {
            var t = typeList[key];
            types[t.name || key] = t;
        });
    }

    var grid = config.columns || 12;
    var selection = null;
    var drag = null;

    function uid() {
        return 'p' + Math.random().toString(36).slice(2, 9);
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function icon(name) {
        var glyph = (config.icons && config.icons[name]) || name || 'square';
        return '<i class="bi bi-' + esc(glyph) + '" aria-hidden="true"></i>';
    }

    function model() {
        return config.layout;
    }

    function sections() {
        return model().sections;
    }

    function clone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function defaultsFor(name) {
        var type = types[name];
        var settings = {};
        if (type && type.fields) {
            Object.keys(type.fields).forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(type.fields[key], 'default')) {
                    settings[key] = type.fields[key]['default'];
                }
            });
        }
        return settings;
    }

    function syncJson() {
        if (jsonArea) {
            jsonArea.value = JSON.stringify(model(), null, 2);
        }
    }

    function sectionAt(index) { return sections()[index]; }
    function columnAt(si, ci) { var s = sectionAt(si); return s && s.columns ? s.columns[ci] : null; }

    function sectionRole(section) {
        var role = section && section.settings ? section.settings.role : '';
        return role === 'header' || role === 'footer' ? role : '';
    }

    function roleLabel(role) {
        return role === 'header' ? 'Шапка сайта' : (role === 'footer' ? 'Подвал сайта' : '');
    }

    function globalCatalog(kind) {
        return (config.globals && config.globals[kind]) || {};
    }

    // Header and footer bands always come first and last, matching the order the
    // renderer uses. Returns the stored index next to the section.
    function displayOrder() {
        var head = [];
        var body = [];
        var tail = [];
        sections().forEach(function (section, si) {
            var role = sectionRole(section);
            if (role === 'header') { head.push({ si: si, section: section }); }
            else if (role === 'footer') { tail.push({ si: si, section: section }); }
            else { body.push({ si: si, section: section }); }
        });
        return head.concat(body, tail);
    }

    function blockLabel(block) {
        return (types[block.type] && types[block.type].title) || block.type;
    }

    function blockIcon(block) {
        return (types[block.type] && types[block.type].icon) || 'square';
    }

    function render() {
        renderLibrary();
        renderGlobals();
        renderCanvas();
        renderSettings();
        syncJson();
        var total = sections().reduce(function (n, s) {
            return n + (s.columns || []).reduce(function (m, c) { return m + (c.blocks || []).length; }, 0);
        }, 0);
        if (countEl) {
            countEl.textContent = sections().length + ' секц. · ' + total + ' элем.';
        }
    }

    function renderLibrary(filter) {
        var grouped = config.categories || {};
        var q = (filter || '').trim().toLowerCase();
        var html = '';
        Object.keys(grouped).forEach(function (category) {
            var items = grouped[category].filter(function (t) {
                return q === '' || t.title.toLowerCase().indexOf(q) !== -1 || t.name.indexOf(q) !== -1;
            });
            if (!items.length) {
                return;
            }
            html += '<div class="builder__cat"><div class="builder__cat-title">' + esc(category) + '</div>';
            items.forEach(function (t) {
                html += '<button type="button" class="builder__particle" draggable="true" data-type="' + esc(t.name) + '">'
                    + icon(t.icon) + '<span>' + esc(t.title) + '</span></button>';
            });
            html += '</div>';
        });
        libraryEl.innerHTML = html || '<p class="muted">Ничего не найдено.</p>';
    }

    function renderGlobals() {
        if (!globalsEl) { return; }
        var sectionsGlobals = globalCatalog('section');
        var blockGlobals = globalCatalog('block');
        var html = '';

        Object.keys(sectionsGlobals).forEach(function (name) {
            html += '<button type="button" class="builder__particle builder__particle--global" draggable="true"'
                + ' data-global-section="' + esc(name) + '">'
                + icon('section') + '<span>' + esc(sectionsGlobals[name].title) + '</span></button>';
        });
        Object.keys(blockGlobals).forEach(function (name) {
            html += '<button type="button" class="builder__particle builder__particle--global" draggable="true"'
                + ' data-global-block="' + esc(name) + '">'
                + icon('global') + '<span>' + esc(blockGlobals[name].title) + '</span></button>';
        });

        globalsEl.innerHTML = html || '<p class="muted">Пока нет глобальных секций.</p>';
    }

    function renderCanvas() {
        if (!sections().length) {
            canvas.innerHTML = '<div class="builder__empty">'
                + '<p>Макет пуст.</p>'
                + '<p class="muted">Перетащите элемент из библиотеки слева или добавьте секцию.</p>'
                + '</div>';
            return;
        }

        var html = '';
        displayOrder().forEach(function (entry) {
            var section = entry.section;
            var si = entry.si;
            var role = sectionRole(section);
            var isGlobal = !!(section.settings && section.settings.global);
            var selected = selection && selection.kind === 'section' && selection.path[0] === si;
            html += '<section class="builder__section' + (selected ? ' is-selected' : '')
                + (role ? ' builder__section--' + role : '') + '" data-si="' + si + '">';
            html += '<header class="builder__section-head">'
                + '<span class="builder__handle" draggable="true" data-drag="section" data-si="' + si + '" title="Перетащить секцию">' + icon('drag') + '</span>'
                + '<span class="builder__section-title">' + (role ? esc(roleLabel(role)) : 'Секция ' + (si + 1)) + '</span>'
                + (isGlobal ? '<span class="builder__badge">' + icon('global') + ' глобальная</span>' : '')
                + '<span class="builder__section-meta">' + (section.columns || []).length + ' кол.</span>'
                + '<span class="builder-bar__spacer"></span>'
                + '<button type="button" class="builder__icon-btn" data-section-dup="' + si + '" title="Дублировать">' + icon('copy') + '</button>'
                + '<button type="button" class="builder__icon-btn" data-section-del="' + si + '" title="Удалить секцию">' + icon('delete') + '</button>'
                + '</header>';

            if (isGlobal && !(section.columns || []).length) {
                var ref = globalCatalog('section')[section.settings.global];
                html += '<p class="builder__global-ref">' + icon('global') + ' Содержимое берётся из глобальной секции <strong>'
                    + esc(ref ? ref.title : section.settings.global) + '</strong>. '
                    + '<a href="/admin/builder?global=section:' + encodeURIComponent(section.settings.global) + '">Редактировать</a></p>';
                html += '</section>';
                return;
            }

            html += '<div class="builder__columns">';
            (section.columns || []).forEach(function (column, ci) {
                var span = column.width > 0 ? column.width : grid;
                html += '<div class="builder__column" data-si="' + si + '" data-ci="' + ci + '" style="flex:' + span + ' 1 0">';
                html += '<div class="builder__column-head"><span>' + span + '/' + grid + '</span>'
                    + '<button type="button" class="builder__icon-btn" data-col-del="' + si + ':' + ci + '" title="Удалить колонку">' + icon('close') + '</button></div>';
                html += '<div class="builder__drop" data-drop="blocks" data-si="' + si + '" data-ci="' + ci + '">';
                (column.blocks || []).forEach(function (block, bi) {
                    html += renderBlockCard(block, si, ci, bi);
                });
                html += '</div>';
                html += '<div class="builder__drop-hint">Перетащите элемент сюда</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</section>';
        });
        canvas.innerHTML = html;
    }

    function renderBlockCard(block, si, ci, bi) {
        var selected = selection && selection.kind === 'block'
            && selection.path[0] === si && selection.path[1] === ci && selection.path[2] === bi;
        var summary = summaryFor(block);
        return '<article class="builder__block' + (selected ? ' is-selected' : '') + '" draggable="true"'
            + ' data-drag="block" data-si="' + si + '" data-ci="' + ci + '" data-bi="' + bi + '">'
            + '<span class="builder__handle">' + icon('drag') + '</span>'
            + '<span class="builder__block-icon">' + icon(blockIcon(block)) + '</span>'
            + '<span class="builder__block-body"><span class="builder__block-title">' + esc(blockLabel(block)) + '</span>'
            + (summary ? '<span class="builder__block-summary">' + esc(summary) + '</span>' : '') + '</span>'
            + '<button type="button" class="builder__icon-btn" data-block-del="' + si + ':' + ci + ':' + bi + '" title="Удалить">' + icon('delete') + '</button>'
            + '</article>';
    }

    function summaryFor(block) {
        if (block.type === 'global' && block.settings && block.settings.name) {
            var ref = globalCatalog('block')[block.settings.name];
            return ref ? ref.title : block.settings.name;
        }
        var type = types[block.type];
        if (!type || !type.fields || !block.settings) {
            return '';
        }
        var keys = Object.keys(type.fields);
        for (var i = 0; i < keys.length; i++) {
            var v = block.settings[keys[i]];
            if (v !== undefined && v !== null && String(v) !== '') {
                return String(v).slice(0, 60);
            }
        }
        return '';
    }

    /**
     * Promote the selected section or block to a global, then swap the local copy
     * for a reference and save the page. The page keeps only a pointer, so later
     * edits to the global show up here without a second copy.
     */
    function promote(kind, si, ci, bi) {
        var title = window.prompt(kind === 'section'
            ? 'Название глобальной секции (например, «Шапка сайта»)'
            : 'Название глобального элемента', kind === 'section' ? 'Шапка сайта' : '');
        if (title === null) { return; }

        var body = new URLSearchParams();
        body.set('action', 'promote');
        body.set('kind', kind);
        body.set('title', title);
        body.set('layout_json', JSON.stringify(model()));
        if (kind === 'section') {
            body.set('index', String(si));
        } else {
            body.set('block_index', String(ci) + ':' + String(bi));
        }

        fetch('/admin/builder/global', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (response) { return response.json(); }).then(function (result) {
            if (!result || !result.name) {
                window.alert((result && result.error) || 'Не удалось сделать глобальным.');
                return;
            }
            replaceWithReference(kind, si, ci, bi, result.name);
            config.globals = config.globals || {};
            config.globals[kind] = config.globals[kind] || {};
            config.globals[kind][result.name] = { name: result.name, title: title, kind: kind };
            render();
            if (form) { form.submit(); }
        }).catch(function () {
            window.alert('Не удалось сделать глобальным.');
        });
    }

    function replaceWithReference(kind, si, ci, bi, name) {
        if (kind === 'section') {
            var section = sectionAt(si);
            if (section) {
                var settings = { global: name };
                var role = sectionRole(section);
                if (role) { settings.role = role; }
                sections()[si] = { id: section.id || uid(), settings: settings, columns: [] };
            }
            return;
        }
        var column = columnAt(si, ci);
        if (column && column.blocks && column.blocks[bi]) {
            column.blocks[bi] = { type: 'global', id: column.blocks[bi].id || uid(), settings: { name: name } };
        }
    }

    function renderSettings() {
        if (!selection) {
            settingsBody.innerHTML = '<p class="muted">Выберите секцию или элемент, чтобы настроить его.</p>';
            return;
        }
        if (selection.kind === 'section') {
            renderSectionSettings(selection.path[0]);
        } else {
            renderBlockSettings(selection.path[0], selection.path[1], selection.path[2]);
        }
    }

    function renderSectionSettings(si) {
        var section = sectionAt(si);
        if (!section) { return; }
        var s = section.settings || (section.settings = {});
        var role = sectionRole(section);
        var globalName = String(s.global || '');
        var html = '<div class="builder__settings-title">' + icon('section') + ' '
            + (role ? esc(roleLabel(role)) : 'Секция ' + (si + 1)) + '</div>';

        if (globalName) {
            html += '<div class="builder__global-note">'
                + icon('global') + ' Глобальная секция <code>' + esc(globalName) + '</code>'
                + '<p class="muted">Правки здесь видны на всех страницах, где она используется.</p>'
                + '</div>';
        }

        html += '<label class="builder__setting">Роль'
            + '<select data-setting="section:role">'
            + option('', 'Обычная секция', role)
            + option('header', 'Шапка сайта', role)
            + option('footer', 'Подвал сайта', role)
            + '</select></label>';

        html += '<label class="builder__setting">Ширина'
            + '<select data-setting="section:width">'
            + option('boxed', 'По контейнеру', s.width || 'boxed')
            + option('full', 'На всю ширину', s.width || 'boxed')
            + '</select></label>';

        html += '<label class="builder__setting">Вариант'
            + '<select data-setting="section:variant">'
            + option('', 'Обычный', s.variant || '')
            + option('muted', 'Серый фон', s.variant || '')
            + option('dark', 'Тёмный', s.variant || '')
            + option('accent', 'Акцентный', s.variant || '')
            + '</select></label>';

        html += '<label class="builder__setting">Фон (CSS)'
            + '<input type="text" data-setting="section:background" value="' + esc(s.background || '') + '" placeholder="#f5f5f5 или var(--accent)"></label>';

        html += '<label class="builder__setting">Отступы (CSS)'
            + '<input type="text" data-setting="section:padding" value="' + esc(s.padding || '') + '" placeholder="3rem 1rem"></label>';

        html += '<div class="builder__settings-actions">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-col-add="' + si + '">' + icon('columns') + ' Добавить колонку</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger" data-section-del="' + si + '">' + icon('delete') + ' Удалить секцию</button>'
            + '</div>';

        html += '<div class="builder__settings-actions builder__settings-actions--globals">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-promote-section="' + si + '">'
            + icon('global') + ' Сделать глобальной</button>'
            + '</div>';

        settingsBody.innerHTML = html;
    }

    function renderFilters(key, filters, props) {
        var html = '<div class="builder__filters">';
        html += '<div class="builder__filters-head">' + esc('Фильтры по свойствам') + '</div>';
        if (!filters.length) {
            html += '<p class="muted">Нет фильтров — выводятся все элементы типа.</p>';
        }
        filters.forEach(function (filter, i) {
            var field = filter.field || '';
            var op = filter.op || '=';
            var value = filter.value === undefined ? '' : filter.value;
            html += '<div class="builder__filter" data-filter-index="' + i + '">';
            html += '<select data-filter-field="' + i + '">';
            html += option('', '— свойство —', field);
            props.forEach(function (p) {
                html += option(p.name, (p.label || p.name), field);
            });
            html += '</select>';
            html += '<select data-filter-op="' + i + '">';
            [['=', 'равно'], ['!=', 'не равно'], ['contains', 'содержит'], ['>', 'больше'], ['<', 'меньше']].forEach(function (pair) {
                html += option(pair[0], pair[1], op);
            });
            html += '</select>';
            var propType = '';
            props.forEach(function (p) { if (p.name === field) { propType = p.type || ''; } });
            if (propType === 'bool') {
                html += '<select data-filter-value="' + i + '">';
                html += option('1', 'да', String(value));
                html += option('0', 'нет', String(value));
                html += '</select>';
            } else {
                html += '<input type="text" data-filter-value="' + i + '" value="' + esc(value) + '" placeholder="значение">';
            }
            html += '<button type="button" class="builder__icon-btn" data-filter-remove="' + i + '" title="Удалить фильтр">' + icon('close') + '</button>';
            html += '</div>';
        });
        html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-filter-add="1">' + icon('add') + ' Добавить фильтр</button>';
        html += '</div>';

        return html;
    }

    function renderBlockSettings(si, ci, bi) {
        var column = columnAt(si, ci);
        var block = column && column.blocks ? column.blocks[bi] : null;
        if (!block) { return; }
        var type = types[block.type];
        var settings = block.settings || (block.settings = {});

        var html = '<div class="builder__settings-title">' + icon(blockIcon(block)) + ' ' + esc(blockLabel(block)) + '</div>';

        if (type && type.fields) {
            Object.keys(type.fields).forEach(function (key) {
                var field = type.fields[key];
                var label = field.label || key;
                var value = Object.prototype.hasOwnProperty.call(settings, key)
                    ? settings[key]
                    : (field['default'] !== undefined ? field['default'] : '');
                html += '<label class="builder__setting">' + esc(label);

                if (key === 'content_type') {
                    html += '<select data-setting="block:' + esc(key) + '">';
                    Object.keys(config.menu || {}).forEach(function (name) {
                        html += option(name, (config.menu[name].label || name), value);
                    });
                    html += '</select></label>';
                    return;
                }

                if (key === 'filters') {
                    var chosenType = settings.content_type || 'page';
                    var props = (config.menu && config.menu[chosenType] && config.menu[chosenType].properties) || [];
                    html += '</label>';
                    html += renderFilters(key, settings[key] || [], props);
                    return;
                }

                if (key === 'element_type') {
                    html += '<select data-setting="block:' + esc(key) + '">';
                    html += option('', '— выберите тип —', value);
                    Object.keys(config.elements || {}).forEach(function (name) {
                        html += option(name, (config.elements[name].label || name), value);
                    });
                    html += '</select></label>';
                    return;
                }

                if (key.indexOf('map_') === 0) {
                    var chosen = settings.element_type || '';
                    var fields = (config.elements && config.elements[chosen] && config.elements[chosen].fields) || [];
                    html += '<select data-setting="block:' + esc(key) + '">';
                    html += option('', '— не выводить —', value);
                    fields.forEach(function (name) { html += option(name, name, value); });
                    html += '</select>';
                    if (fields.length === 0) {
                        html += '<small class="muted">Сначала выберите тип элемента.</small>';
                    }
                    html += '</label>';
                    return;
                }

                if (field.type === 'file') {
                    html += '<input type="text" data-setting="block:' + esc(key) + '" value="' + esc(value) + '" placeholder="/media/…">';
                    html += '<input type="file" data-upload="block:' + esc(key) + '">';
                    var media = config.media || [];
                    if (media.length) {
                        var mediaBase = config.mediaId || config.pageId;
                        html += '<select data-pick="block:' + esc(key) + '">';
                        html += option('', '— выбрать из медиа —', '');
                        media.forEach(function (name) {
                            html += option('/media/' + mediaBase + '/' + name, name, '');
                        });
                        html += '</select>';
                    }
                    html += '</label>';
                    return;
                }

                if (field.type === 'select' && Array.isArray(field.options)) {
                    html += '<select data-setting="block:' + esc(key) + '">';
                    field.options.forEach(function (opt) {
                        html += option(opt, opt, value);
                    });
                    html += '</select>';
                } else if (field.type === 'bool') {
                    html += '<input type="checkbox" data-setting="block:' + esc(key) + '" value="1"' + (value ? ' checked' : '') + '>';
                } else if (field.type === 'markdown' || field.type === 'text') {
                    html += '<textarea rows="3" data-setting="block:' + esc(key) + '">' + esc(value) + '</textarea>';
                } else {
                    var inputType = field.type === 'int' || field.type === 'float' ? 'number' : 'text';
                    var step = field.type === 'float' ? ' step="any"' : '';
                    html += '<input type="' + inputType + '"' + step + ' data-setting="block:' + esc(key) + '" value="' + esc(value) + '">';
                }
                html += '</label>';
            });
        } else {
            html += '<p class="muted">У этого элемента нет настроек.</p>';
        }

        html += '<div class="builder__settings-actions">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-block-dup="' + si + ':' + ci + ':' + bi + '">' + icon('duplicate') + ' Дублировать</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger" data-block-del="' + si + ':' + ci + ':' + bi + '">' + icon('delete') + ' Удалить</button>'
            + '</div>';

        if (block.type !== 'global') {
            html += '<div class="builder__settings-actions builder__settings-actions--globals">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary" data-promote-block="' + si + ':' + ci + ':' + bi + '">'
                + icon('global') + ' Сделать глобальным</button>'
                + '</div>';
        }

        settingsBody.innerHTML = html;
    }

    function option(value, label, current) {
        return '<option value="' + esc(value) + '"' + (String(current) === String(value) ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function presetBlock(entry) {
        var type = entry && entry.type ? entry.type : '';
        if (!type) { return null; }
        var settings = defaultsFor(type);
        if (entry.settings && typeof entry.settings === 'object') {
            Object.keys(entry.settings).forEach(function (key) {
                settings[key] = entry.settings[key];
            });
        }
        return { type: type, id: uid(), settings: settings };
    }

    function addSection(preset) {
        // Accepts a preset object ({widths, blocks, position}) or a bare widths array.
        var widths = Array.isArray(preset) ? preset : (preset.widths || [grid]);
        var presetBlocks = (!Array.isArray(preset) && Array.isArray(preset.blocks)) ? preset.blocks : [];
        var columns = widths.map(function (w, ci) {
            var blocks = [];
            (presetBlocks[ci] || []).forEach(function (entry) {
                var block = presetBlock(entry);
                if (block) { blocks.push(block); }
            });
            return { width: w, blocks: blocks };
        });
        var settings = {};
        if (!Array.isArray(preset) && (preset.role === 'header' || preset.role === 'footer')) {
            settings.role = preset.role;
        }
        var section = { id: uid(), settings: settings, columns: columns };
        // A "start" preset (the header) goes above the existing sections.
        if (!Array.isArray(preset) && preset.position === 'start') {
            sections().unshift(section);
        } else {
            sections().push(section);
        }
        render();
    }

    /**
     * Insert a reference to a global section. The reference carries only the name
     * and an optional local override; the renderer fills in the shared content.
     */
    function addGlobalSection(name) {
        var reference = {
            id: uid(),
            settings: { global: name },
            columns: []
        };
        // A header global belongs at the top and a footer at the bottom.
        var entry = globalCatalog('section')[name];
        var role = entry && entry.role ? entry.role : '';
        if (role === 'footer') {
            sections().push(reference);
        } else {
            sections().unshift(reference);
        }
        render();
    }

    function addBlock(typeName, si, ci, index) {
        var column = columnAt(si, ci);
        if (!column) { return; }
        var block = { type: typeName, id: uid(), settings: defaultsFor(typeName) };
        if (index === undefined || index === null || index > column.blocks.length) {
            column.blocks.push(block);
        } else {
            column.blocks.splice(index, 0, block);
        }
        selection = { kind: 'block', path: [si, ci, column.blocks.indexOf(block)] };
        render();
    }

    function removeBlock(si, ci, bi) {
        var column = columnAt(si, ci);
        if (column && column.blocks) {
            column.blocks.splice(bi, 1);
            selection = null;
            render();
        }
    }

    function duplicateBlock(si, ci, bi) {
        var column = columnAt(si, ci);
        if (!column || !column.blocks || !column.blocks[bi]) { return; }
        var copy = clone(column.blocks[bi]);
        copy.id = uid();
        column.blocks.splice(bi + 1, 0, copy);
        selection = { kind: 'block', path: [si, ci, bi + 1] };
        render();
    }

    function moveBlock(from, to) {
        var fromCol = columnAt(from.si, from.ci);
        var toCol = columnAt(to.si, to.ci);
        if (!fromCol || !toCol) { return; }
        var block = fromCol.blocks.splice(from.bi, 1)[0];
        var index = to.bi === null || to.bi === undefined ? toCol.blocks.length : to.bi;
        toCol.blocks.splice(index, 0, block);
        render();
    }

    function moveSection(from, to) {
        var arr = sections();
        var moved = arr.splice(from, 1)[0];
        arr.splice(to, 0, moved);
        selection = null;
        render();
    }

    (globalsEl || libraryEl).addEventListener('dragstart', onLibraryDragStart);
    libraryEl.addEventListener('dragstart', onLibraryDragStart);

    function onLibraryDragStart(event) {
        var button = event.target.closest('.builder__particle');
        if (!button) { return; }
        if (button.hasAttribute('data-global-section')) {
            drag = { kind: 'global-section', name: button.getAttribute('data-global-section') };
        } else if (button.hasAttribute('data-global-block')) {
            drag = { kind: 'global-block', name: button.getAttribute('data-global-block') };
        } else {
            drag = { kind: 'new', type: button.getAttribute('data-type') };
        }
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', 'builder-particle');
    }

    canvas.addEventListener('dragstart', function (event) {
        var handle = event.target.closest('[data-drag="section"]');
        if (handle) {
            drag = { kind: 'section', si: parseInt(handle.getAttribute('data-si'), 10) };
            event.dataTransfer.effectAllowed = 'move';
            return;
        }
        var card = event.target.closest('[data-drag="block"]');
        if (card) {
            drag = {
                kind: 'block',
                si: parseInt(card.getAttribute('data-si'), 10),
                ci: parseInt(card.getAttribute('data-ci'), 10),
                bi: parseInt(card.getAttribute('data-bi'), 10)
            };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'block');
        }
    });

    canvas.addEventListener('dragover', function (event) {
        var drop = event.target.closest('[data-drop="blocks"]');
        if (drop) {
            event.preventDefault();
            drop.classList.add('is-over');
            highlightDropTarget(drop, event);
        }
        var section = event.target.closest('.builder__section');
        if (drag && drag.kind === 'section' && section) {
            event.preventDefault();
            section.classList.add('is-dragover');
        }
    });

    canvas.addEventListener('dragleave', function (event) {
        var drop = event.target.closest('[data-drop="blocks"]');
        if (drop) { drop.classList.remove('is-over'); }
        var section = event.target.closest('.builder__section');
        if (section) { section.classList.remove('is-dragover'); }
    });

    canvas.addEventListener('drop', function (event) {
        var drop = event.target.closest('[data-drop="blocks"]');
        if (drop) {
            event.preventDefault();
            drop.classList.remove('is-over');
            var si = parseInt(drop.getAttribute('data-si'), 10);
            var ci = parseInt(drop.getAttribute('data-ci'), 10);
            var index = dropIndex(drop, event);
            if (drag && drag.kind === 'new') {
                addBlock(drag.type, si, ci, index);
            } else if (drag && drag.kind === 'global-block') {
                addBlock('global', si, ci, index);
                var col = columnAt(si, ci);
                var added = col && col.blocks ? col.blocks[index === null ? col.blocks.length - 1 : index] : null;
                if (added) {
                    added.settings = added.settings || {};
                    added.settings.name = drag.name;
                }
                render();
            } else if (drag && drag.kind === 'block') {
                if (drag.si === si && drag.ci === ci && (index === null || index === drag.bi || index === drag.bi + 1)) {
                    drag = null;
                    return;
                }
                moveBlock({ si: drag.si, ci: drag.ci, bi: drag.bi }, { si: si, ci: ci, bi: index });
            }
            drag = null;
            return;
        }

        var section = event.target.closest('.builder__section');
        if (drag && drag.kind === 'global-section') {
            event.preventDefault();
            addGlobalSection(drag.name);
            drag = null;
            return;
        }
        if (drag && drag.kind === 'section' && section) {
            event.preventDefault();
            var to = parseInt(section.getAttribute('data-si'), 10);
            section.classList.remove('is-dragover');
            if (to !== drag.si) { moveSection(drag.si, to); }
            drag = null;
        }
    });

    function highlightDropTarget(drop, event) {
        var cards = Array.prototype.slice.call(drop.querySelectorAll('.builder__block'));
        cards.forEach(function (c) { c.classList.remove('is-before', 'is-after'); });
        var card = event.target.closest('.builder__block');
        if (!card) { return; }
        var rect = card.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        card.classList.add(after ? 'is-after' : 'is-before');
    }

    function dropIndex(drop, event) {
        var card = event.target.closest('.builder__block');
        if (!card) { return null; }
        var bi = parseInt(card.getAttribute('data-bi'), 10);
        var rect = card.getBoundingClientRect();
        return event.clientY > rect.top + rect.height / 2 ? bi + 1 : bi;
    }

    canvas.addEventListener('click', onEditorClick);
    // The settings panel repeats delete/duplicate actions, so share the handler.
    settingsBody.addEventListener('click', onEditorClick);

    function onEditorClick(event) {
        var filterAdd = event.target.closest('[data-filter-add]');
        if (filterAdd) {
            var filters = currentBlockFilters();
            if (filters) {
                filters.push({ field: '', op: '=', value: '' });
                renderSettings();
            }
            return;
        }
        var filterRemove = event.target.closest('[data-filter-remove]');
        if (filterRemove) {
            var list = currentBlockFilters();
            if (list) {
                list.splice(parseInt(filterRemove.getAttribute('data-filter-remove'), 10), 1);
                renderSettings();
            }
            return;
        }
        var promoteSection = event.target.closest('[data-promote-section]');
        if (promoteSection) {
            promote('section', parseInt(promoteSection.getAttribute('data-promote-section'), 10), null, null);
            return;
        }
        var promoteBlock = event.target.closest('[data-promote-block]');
        if (promoteBlock) {
            var pp = promoteBlock.getAttribute('data-promote-block').split(':').map(Number);
            promote('block', pp[0], pp[1], pp[2]);
            return;
        }
        var del = event.target.closest('[data-block-del]');
        if (del) {
            var p = del.getAttribute('data-block-del').split(':').map(Number);
            removeBlock(p[0], p[1], p[2]);
            return;
        }
        var dup = event.target.closest('[data-block-dup]');
        if (dup) {
            var pd = dup.getAttribute('data-block-dup').split(':').map(Number);
            duplicateBlock(pd[0], pd[1], pd[2]);
            return;
        }
        var secDel = event.target.closest('[data-section-del]');
        if (secDel) {
            sections().splice(parseInt(secDel.getAttribute('data-section-del'), 10), 1);
            selection = null;
            render();
            return;
        }
        var secDup = event.target.closest('[data-section-dup]');
        if (secDup) {
            var si = parseInt(secDup.getAttribute('data-section-dup'), 10);
            var copy = clone(sectionAt(si));
            copy.id = uid();
            sections().splice(si + 1, 0, copy);
            render();
            return;
        }
        var colDel = event.target.closest('[data-col-del]');
        if (colDel) {
            var pc = colDel.getAttribute('data-col-del').split(':').map(Number);
            var cols = sectionAt(pc[0]).columns;
            if (cols.length > 1) {
                cols.splice(pc[1], 1);
                selection = null;
                render();
            }
            return;
        }
        var colAdd = event.target.closest('[data-col-add]');
        if (colAdd) {
            var sai = parseInt(colAdd.getAttribute('data-col-add'), 10);
            sectionAt(sai).columns.push({ width: 0, blocks: [] });
            render();
            return;
        }

        var card = event.target.closest('.builder__block');
        if (card && !event.target.closest('.builder__icon-btn')) {
            selection = {
                kind: 'block',
                path: [parseInt(card.getAttribute('data-si'), 10), parseInt(card.getAttribute('data-ci'), 10), parseInt(card.getAttribute('data-bi'), 10)]
            };
            render();
            return;
        }

        var section = event.target.closest('.builder__section');
        if (section) {
            selection = { kind: 'section', path: [parseInt(section.getAttribute('data-si'), 10)] };
            render();
        }
    }

    settingsBody.addEventListener('input', onSettingChange);
    settingsBody.addEventListener('change', onSettingChange);
    settingsBody.addEventListener('input', onFilterChange);
    settingsBody.addEventListener('change', onFilterChange);

    // Menu particle: rebuild the property-filter builder when the content type
    // (and therefore the available properties) changes.
    settingsBody.addEventListener('change', function (event) {
        var el = event.target.closest('[data-setting]');
        if (el && el.getAttribute('data-setting') === 'block:content_type') {
            var filters = currentBlockFilters();
            if (filters) {
                filters.forEach(function (f) { f.field = ''; });
            }
            renderSettings();
        }
    });

    // Element-list particle: when the element type changes, rebuild the mapping
    // selects so they list the properties of the newly chosen type.
    settingsBody.addEventListener('change', function (event) {
        var el = event.target.closest('[data-setting]');
        if (el && el.getAttribute('data-setting') === 'block:element_type') {
            renderSettings();
        }
    });

    settingsBody.addEventListener('change', function (event) {
        var pick = event.target.closest('[data-pick]');
        if (!pick || !selection || selection.kind !== 'block') { return; }
        if (pick.value === '') { return; }
        setBlockSetting(pick.getAttribute('data-pick').split(':')[1], pick.value);
    });

    settingsBody.addEventListener('change', function (event) {
        var input = event.target.closest('[data-upload]');
        if (!input || !input.files || !input.files.length || !config.uploadUrl) { return; }
        var key = input.getAttribute('data-upload').split(':')[1];
        var data = new FormData();
        data.append('id', config.pageId);
        data.append('file', input.files[0]);

        fetch(config.uploadUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.error) {
                    window.alert(result.error);
                    return;
                }
                config.media = result.media || config.media;
                setBlockSetting(key, result.url);
                renderSettings();
            })
            .catch(function () { window.alert('Не удалось загрузить файл.'); });
    });

    function currentBlockFilters() {
        if (!selection || selection.kind !== 'block') { return null; }
        var column = columnAt(selection.path[0], selection.path[1]);
        var block = column && column.blocks ? column.blocks[selection.path[2]] : null;
        if (!block) { return null; }
        block.settings = block.settings || {};
        if (!Array.isArray(block.settings.filters)) {
            block.settings.filters = [];
        }

        return block.settings.filters;
    }

    function setBlockSetting(key, value) {
        if (!selection || selection.kind !== 'block') { return; }
        var column = columnAt(selection.path[0], selection.path[1]);
        var block = column && column.blocks ? column.blocks[selection.path[2]] : null;
        if (!block) { return; }
        block.settings = block.settings || {};
        block.settings[key] = value;
        syncJson();
        updateCardSummary();
        renderSettings();
    }

    function onFilterChange(event) {
        var el = event.target.closest('[data-filter-field], [data-filter-op], [data-filter-value]');
        if (!el || !selection || selection.kind !== 'block') { return; }
        var filters = currentBlockFilters();
        if (!filters) { return; }
        var index = parseInt(el.getAttribute('data-filter-field') !== null
            ? el.getAttribute('data-filter-field')
            : (el.getAttribute('data-filter-op') !== null ? el.getAttribute('data-filter-op') : el.getAttribute('data-filter-value')), 10);
        if (!filters[index]) { return; }
        if (el.hasAttribute('data-filter-field')) { filters[index].field = el.value; }
        else if (el.hasAttribute('data-filter-op')) { filters[index].op = el.value; }
        else { filters[index].value = el.value; }
        syncJson();
    }

    function onSettingChange(event) {
        var el = event.target.closest('[data-setting]');
        if (!el || !selection) { return; }
        var parts = el.getAttribute('data-setting').split(':');
        var scope = parts[0];
        var key = parts[1];
        var value = el.type === 'checkbox' ? (el.checked ? true : false) : el.value;

        if (scope === 'section') {
            var section = sectionAt(selection.path[0]);
            if (section) {
                section.settings = section.settings || {};
                section.settings[key] = value;
            }
        } else if (scope === 'block') {
            var column = columnAt(selection.path[0], selection.path[1]);
            var block = column && column.blocks ? column.blocks[selection.path[2]] : null;
            if (block) {
                block.settings = block.settings || {};
                block.settings[key] = value;
            }
        }
        syncJson();
        updateCardSummary();
    }

    function updateCardSummary() {
        if (!selection || selection.kind !== 'block') { return; }
        var card = canvas.querySelector('.builder__block.is-selected .builder__block-summary');
        var column = columnAt(selection.path[0], selection.path[1]);
        var block = column && column.blocks ? column.blocks[selection.path[2]] : null;
        if (card && block) {
            card.textContent = summaryFor(block);
        }
    }

    presetsEl.addEventListener('click', function (event) {
        var button = event.target.closest('[data-preset]');
        if (!button) { return; }
        try {
            addSection(JSON.parse(button.getAttribute('data-preset')));
        } catch (e) {
            addSection([grid]);
        }
    });

    if (globalsEl) {
        globalsEl.addEventListener('click', function (event) {
            var section = event.target.closest('[data-global-section]');
            if (section) {
                addGlobalSection(section.getAttribute('data-global-section'));
                return;
            }
            var block = event.target.closest('[data-global-block]');
            if (block) {
                // Add to the first column of the last body section, or start one.
                var target = null;
                displayOrder().forEach(function (entry) {
                    if (!sectionRole(entry.section)) { target = entry; }
                });
                if (!target) {
                    addSection([grid]);
                    target = { si: sections().length - 1 };
                }
                var column = columnAt(target.si, 0);
                if (column) {
                    addBlock('global', target.si, 0, column.blocks.length);
                    var added = columnAt(target.si, 0).blocks.slice(-1)[0];
                    if (added) {
                        added.settings = added.settings || {};
                        added.settings.name = block.getAttribute('data-global-block');
                    }
                    render();
                }
            }
        });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            renderLibrary(searchEl.value);
        });
    }

    var jsonToggle = document.querySelector('[data-builder-toggle="json"]');
    if (jsonToggle) {
        jsonToggle.addEventListener('click', function () {
            jsonWrap.hidden = !jsonWrap.hidden;
        });
    }

    var backButton = document.querySelector('[data-builder-back]');
    if (backButton) {
        backButton.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/admin/pages';
            }
        });
    }

    if (jsonArea) {
        jsonArea.addEventListener('change', function () {
            try {
                var data = JSON.parse(jsonArea.value);
                config.layout = data.sections ? data : { sections: data.blocks || [] };
                selection = null;
                render();
            } catch (e) {
                /* keep the invalid text so the user can fix it */
            }
        });
    }

    render();
})();
