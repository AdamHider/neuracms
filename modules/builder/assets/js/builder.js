/*
 * Gantry-style page builder.
 *
 * The layout is a list of sections; a section holds rows, a row holds columns and
 * a column holds blocks ("particles"). Every structural level is a node with its
 * own settings, so one panel edits a section, a row, a column or a block.
 *
 * The JSON textarea stays in sync and is what the server reads, so the editor
 * degrades to raw JSON editing without JS.
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

    var JUSTIFY = [['', 'по умолчанию'], ['start', 'в начало'], ['center', 'по центру'], ['end', 'в конец'],
        ['between', 'равномерно'], ['around', 'с отступами'], ['evenly', 'равные отступы']];
    var ALIGN = [['', 'по умолчанию'], ['start', 'в начало'], ['center', 'по центру'], ['end', 'в конец'],
        ['stretch', 'растянуть'], ['baseline', 'по базовой линии']];

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
        if (!Array.isArray(model().sections)) {
            model().sections = [];
        }

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

    function sectionAt(si) { return sections()[si]; }

    function rowAt(si, ri) {
        var section = sectionAt(si);
        return section && section.rows ? section.rows[ri] : null;
    }

    function columnAt(si, ri, ci) {
        var row = rowAt(si, ri);
        return row && row.columns ? row.columns[ci] : null;
    }

    function blockAt(si, ri, ci, bi) {
        var column = columnAt(si, ri, ci);
        return column && column.blocks ? column.blocks[bi] : null;
    }

    function nodeAt(path) {
        if (path.length === 1) { return sectionAt(path[0]); }
        if (path.length === 2) { return rowAt(path[0], path[1]); }
        if (path.length === 3) { return columnAt(path[0], path[1], path[2]); }

        return blockAt(path[0], path[1], path[2], path[3]);
    }

    function rowsOf(section) {
        if (!Array.isArray(section.rows)) {
            section.rows = [];
        }

        return section.rows;
    }

    function columnsOf(row) {
        if (!Array.isArray(row.columns)) {
            row.columns = [];
        }

        return row.columns;
    }

    function blocksOf(column) {
        if (!Array.isArray(column.blocks)) {
            column.blocks = [];
        }

        return column.blocks;
    }

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
        if (block.title) { return block.title; }

        return (types[block.type] && types[block.type].title) || block.type;
    }

    function blockIcon(block) {
        return (types[block.type] && types[block.type].icon) || 'square';
    }

    function isSelected(path) {
        return !!selection && selection.path.length === path.length
            && selection.path.every(function (value, i) { return value === path[i]; });
    }

    function select(kind, path) {
        selection = { kind: kind, path: path };
    }

    function render() {
        renderLibrary();
        renderGlobals();
        renderCanvas();
        renderSettings();
        syncJson();
        renderCount();
    }

    function renderCount() {
        var blocks = 0;
        var rows = 0;
        sections().forEach(function (section) {
            (section.rows || []).forEach(function (row) {
                rows++;
                (row.columns || []).forEach(function (column) {
                    blocks += (column.blocks || []).length;
                });
            });
        });
        if (countEl) {
            countEl.textContent = sections().length + ' секц. · ' + rows + ' ряд. · ' + blocks + ' элем.';
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
        var blockGlobals = globalCatalog('block');
        var html = '';

        Object.keys(blockGlobals).forEach(function (name) {
            html += '<button type="button" class="builder__particle builder__particle--global" draggable="true"'
                + ' data-global-block="' + esc(name) + '">'
                + icon('global') + '<span>' + esc(blockGlobals[name].title) + '</span></button>';
        });

        // Global sections are not particles: a section is not something you drop
        // into a column, so they live in the "Добавить секцию" menu instead.
        globalsEl.innerHTML = html || '<p class="muted">Пока нет глобальных элементов.</p>';
    }

    // The stored structure of a global, so a reference can be drawn with the same
    // section/row/column/block cards as the page itself. Rendering the front end
    // here would show published markup instead of something you can inspect.
    function globalStructure(kind, name) {
        var structures = config.globalStructures || {};
        var bucket = structures[kind] || {};

        return bucket[name] || null;
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
            html += renderSection(entry.section, entry.si);
        });
        canvas.innerHTML = html;
    }

    function renderSection(section, si) {
        var role = sectionRole(section);
        var isGlobal = !!(section.settings && section.settings.global);
        var rows = section.rows || [];

        var html = '<section class="builder__section' + (isSelected([si]) ? ' is-selected' : '')
            + (role ? ' builder__section--' + role : '') + '" data-si="' + si + '">';

        html += '<header class="builder__section-head">'
            + '<span class="builder__handle" draggable="true" data-drag="section" data-si="' + si + '" title="Перетащить секцию">' + icon('drag') + '</span>'
            + '<span class="builder__section-title">' + esc(sectionTitle(section, si)) + '</span>'
            + (role ? '<span class="builder__badge">' + esc(roleLabel(role)) + '</span>' : '')
            + (isGlobal ? '<span class="builder__badge">' + icon('global') + ' глобальная</span>' : '')
            + '<span class="builder__section-meta">' + rows.length + ' ряд. · ' + countColumns(section) + ' кол.</span>'
            + '<span class="builder-bar__spacer"></span>'
            + '<button type="button" class="builder__icon-btn" data-row-add="' + si + '" title="Добавить ряд">' + icon('add') + '</button>'
            + (isGlobal ? '' : '<button type="button" class="builder__icon-btn" data-promote-section="' + si + '" title="Сделать глобальной">' + icon('global') + '</button>')
            + '<button type="button" class="builder__icon-btn" data-section-dup="' + si + '" title="Дублировать секцию">' + icon('copy') + '</button>'
            + '<button type="button" class="builder__icon-btn" data-section-del="' + si + '" title="Удалить секцию">' + icon('delete') + '</button>'
            + '</header>';

        if (isGlobal && rows.length === 0) {
            var ref = globalCatalog('section')[section.settings.global];
            var structure = globalStructure('section', section.settings.global);
            var sharedRows = (structure && structure.rows) || [];
            html += '<div class="builder__global-body">';
            if (sharedRows.length) {
                // Draw the shared rows exactly as the source page draws them —
                // same cards, handles and inline controls — so a global band is
                // recognisable at a glance. The controls are disabled here and
                // the band links to the global editor, because the content lives
                // in the global rather than in this page.
                html += '<div class="builder__global-shared" data-global-shared="' + esc(section.settings.global) + '">';
                sharedRows.forEach(function (row, ri) {
                    html += renderRow(row, si, ri, true);
                });
                html += '</div>';
            } else {
                html += '<p class="builder__row-empty muted">Глобальная секция пуста.</p>';
            }
            html += '<p class="builder__global-ref">' + icon('global') + ' Содержимое берётся из глобальной секции <strong>'
                + esc(ref ? ref.title : section.settings.global) + '</strong>. '
                + '<a href="/admin/builder?global=section:' + encodeURIComponent(section.settings.global) + '">Редактировать</a></p>';
            html += '</div>';
            html += '</section>';

            return html;
        }

        rows.forEach(function (row, ri) {
            html += renderRow(row, si, ri);
        });

        if (!rows.length) {
            html += '<p class="builder__row-empty muted">В секции нет рядов — добавьте ряд кнопкой в заголовке.</p>';
        }

        html += '</section>';

        return html;
    }

    function sectionTitle(section, si) {
        if (section.title) { return section.title; }
        var role = sectionRole(section);

        return role ? roleLabel(role) : 'Секция ' + (si + 1);
    }

    function countColumns(section) {
        return (section.rows || []).reduce(function (n, row) {
            return n + (row.columns || []).length;
        }, 0);
    }

    // "shared" draws a global's own structure inside a reference. It must look
    // like the source page's editable state — same cards, handles and inline
    // controls — but the content belongs to the global, so the controls are
    // disabled and the whole band links to the global editor instead.
    function renderRow(row, si, ri, shared) {
        var columns = row.columns || [];

        var html = '<div class="builder__row' + (!shared && isSelected([si, ri]) ? ' is-selected' : '')
            + '" data-si="' + si + '" data-ri="' + ri + '">';
        html += '<div class="builder__row-head">'
            + '<span class="builder__handle"' + (shared ? '' : ' draggable="true" data-drag="row" data-si="' + si + '" data-ri="' + ri + '"') + ' title="Перетащить ряд">' + icon('drag') + '</span>'
            + '<span class="builder__row-title">' + esc(row.title || 'Ряд ' + (ri + 1)) + '</span>'
            + '<span class="builder__row-meta">' + columns.length + ' кол.</span>'
            + '<span class="builder-bar__spacer"></span>'
            + '<button type="button" class="builder__icon-btn" data-row-del="' + si + ':' + ri + '" title="Удалить ряд"' + (shared ? ' disabled' : '') + '>' + icon('delete') + '</button>'
            + '</div>';

        html += '<div class="builder__row-columns"' + (shared ? '' : ' data-drop="columns" data-si="' + si + '" data-ri="' + ri + '"') + '>';
        columns.forEach(function (column, ci) {
            html += renderColumn(column, si, ri, ci, shared);
        });
        // The affordance sits inside the row it appends to, so the target is the
        // button itself rather than a control in the settings panel.
        html += '<button type="button" class="builder__add-col" data-col-add="' + si + ':' + ri + '" title="Добавить колонку"' + (shared ? ' disabled' : '') + '>'
            + icon('add') + '<span>Колонка</span></button>';
        html += '</div>';

        if (!columns.length) {
            html += '<p class="builder__row-empty muted">В ряду нет колонок.</p>';
        }

        html += '</div>';

        return html;
    }

    function columnFlex(width) {
        if (!(width > 0)) {
            return 'flex:1 1 0;min-width:120px';
        }
        var pct = (width / grid) * 100;

        return 'flex:0 0 ' + pct + '%;max-width:' + pct + '%;min-width:120px';
    }

    function renderColumn(column, si, ri, ci, shared) {
        var span = column.width > 0 ? column.width : 0;
        var blocks = column.blocks || [];

        var html = '<div class="builder__column' + (!shared && isSelected([si, ri, ci]) ? ' is-selected' : '') + '"'
            + ' data-si="' + si + '" data-ri="' + ri + '" data-ci="' + ci + '"'
            + ' style="' + columnFlex(span) + '">';
        html += '<div class="builder__column-head">'
            + '<span class="builder__handle"' + (shared ? '' : ' draggable="true" data-drag="column" data-si="' + si + '" data-ri="' + ri + '" data-ci="' + ci + '"') + ' title="Перетащить колонку">' + icon('drag') + '</span>'
            + '<span class="builder__column-title">' + esc(column.title || (span > 0 ? span + '/' + grid : 'авто')) + '</span>'
            + '<span class="builder-bar__spacer"></span>'
            + '<button type="button" class="builder__icon-btn" data-col-width-down="' + si + ':' + ri + ':' + ci + '" title="Уже"' + (shared ? ' disabled' : '') + '>' + icon('minus') + '</button>'
            + '<button type="button" class="builder__icon-btn" data-col-width-up="' + si + ':' + ri + ':' + ci + '" title="Шире"' + (shared ? ' disabled' : '') + '>' + icon('plus') + '</button>'
            + '<button type="button" class="builder__icon-btn" data-col-del="' + si + ':' + ri + ':' + ci + '" title="Удалить колонку"' + (shared ? ' disabled' : '') + '>' + icon('close') + '</button>'
            + '</div>';
        html += '<div class="builder__drop"' + (shared ? '' : ' data-drop="blocks" data-si="' + si + '" data-ri="' + ri + '" data-ci="' + ci + '"') + '>';
        blocks.forEach(function (block, bi) {
            html += renderBlockCard(block, si, ri, ci, bi, shared);
        });
        html += '</div>';
        html += '<div class="builder__drop-hint">Перетащите элемент сюда</div>';
        html += '</div>';

        return html;
    }

    function renderBlockCard(block, si, ri, ci, bi, shared) {
        var summary = summaryFor(block);
        var path = si + ':' + ri + ':' + ci + ':' + bi;
        var isGlobalRef = block.type === 'global';
        var structure = isGlobalRef ? globalStructure('block', block.settings && block.settings.name) : null;

        return '<article class="builder__block' + (!shared && isSelected([si, ri, ci, bi]) ? ' is-selected' : '') + '"'
            + (shared ? '' : ' draggable="true" data-drag="block" data-si="' + si + '" data-ri="' + ri + '" data-ci="' + ci + '" data-bi="' + bi + '"') + '>'
            + '<span class="builder__handle">' + icon('drag') + '</span>'
            + '<span class="builder__block-icon">' + icon(blockIcon(block)) + '</span>'
            + '<span class="builder__block-body"><span class="builder__block-title">' + esc(blockLabel(block)) + '</span>'
            + (summary ? '<span class="builder__block-summary">' + esc(summary) + '</span>' : '') + '</span>'
            // A global block shows what the shared block holds, using the same
            // field labels the block editor would show.
            + (structure ? '<span class="builder__block-fields">' + blockFieldsHtml(structure) + '</span>' : '')
            + '<span class="builder__block-actions">'
            + (isGlobalRef ? '' : '<button type="button" class="builder__icon-btn" data-promote-block="' + path + '" title="Сделать глобальным"' + (shared ? ' disabled' : '') + '>' + icon('global') + '</button>')
            + '<button type="button" class="builder__icon-btn" data-block-dup="' + path + '" title="Дублировать"' + (shared ? ' disabled' : '') + '>' + icon('copy') + '</button>'
            + '<button type="button" class="builder__icon-btn" data-block-del="' + path + '" title="Удалить"' + (shared ? ' disabled' : '') + '>' + icon('delete') + '</button>'
            + '</span>'
            + '</article>';
    }

    // A compact read-only view of a block's settings, so a global block reads as
    // content instead of as a bare name.
    function blockFieldsHtml(block) {
        var type = types[block.type];
        var settings = block.settings || {};
        if (!type || !type.fields) { return ''; }
        var parts = [];
        Object.keys(type.fields).forEach(function (key) {
            var value = settings[key];
            if (value === undefined || value === null || typeof value === 'object' || String(value) === '') { return; }
            parts.push('<span class="builder__field"><span class="builder__field-label">'
                + esc(type.fields[key].label || key) + '</span><span class="builder__field-value">'
                + esc(String(value).slice(0, 80)) + '</span></span>');
        });

        return parts.join('');
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
            var value = block.settings[keys[i]];
            if (value !== undefined && value !== null && String(value) !== '') {
                return String(value).slice(0, 60);
            }
        }

        return '';
    }

    /**
     * Promote the selected section or block to a global, then swap the local copy
     * for a reference and save the page.
     */
    function promote(kind, path) {
        var si = path[0];
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
            body.set('block_index', path.join(':'));
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
            replaceWithReference(kind, path, result.name);
            config.globals = config.globals || {};
            config.globals[kind] = config.globals[kind] || {};
            config.globals[kind][result.name] = { name: result.name, title: title, kind: kind };
            render();
            if (form) { form.submit(); }
        }).catch(function () {
            window.alert('Не удалось сделать глобальным.');
        });
    }

    function replaceWithReference(kind, path, name) {
        if (kind === 'section') {
            var section = sectionAt(path[0]);
            if (section) {
                var settings = { global: name };
                var role = sectionRole(section);
                if (role) { settings.role = role; }
                sections()[path[0]] = { id: section.id || uid(), settings: settings, rows: [] };
            }

            return;
        }
        var block = blockAt(path[0], path[1], path[2], path[3]);
        if (block) {
            columnAt(path[0], path[1], path[2]).blocks[path[3]] = {
                type: 'global', id: block.id || uid(), settings: { name: name }
            };
        }
    }

    function renderSettings() {
        if (!selection) {
            settingsBody.innerHTML = '<p class="muted">Выберите секцию, ряд, колонку или элемент, чтобы настроить его.</p>';

            return;
        }
        var node = nodeAt(selection.path);
        if (!node) {
            settingsBody.innerHTML = '<p class="muted">Элемент не найден.</p>';

            return;
        }

        settingsBody.innerHTML = settingsHtml(selection.kind, node, selection.path);
    }

    function settingsHtml(kind, node, path) {
        var html = '<div class="builder__settings-title">' + icon(kind === 'block' ? blockIcon(node) : kind) + ' '
            + esc(settingsTitle(kind, node, path)) + '</div>';

        if (kind === 'block') {
            return html + blockSettingsHtml(node, path);
        }

        return html + nodeSettingsHtml(kind, node, path);
    }

    function settingsTitle(kind, node, path) {
        if (node.title) { return node.title; }
        if (kind === 'section') {
            var role = sectionRole(node);

            return role ? roleLabel(role) : 'Секция ' + (path[0] + 1);
        }
        if (kind === 'row') { return 'Ряд ' + (path[1] + 1); }
        if (kind === 'column') { return 'Колонка ' + (path[2] + 1); }

        return blockLabel(node);
    }

    /**
     * Title and CSS class are offered on every node, which is what makes the
     * editor uniform: a section, a row, a column and a block all answer to the
     * same two fields.
     */
    function commonSettingsHtml(node) {
        var s = node.settings || {};

        return '<label class="builder__setting">Название в редакторе'
            + '<input type="text" data-setting="node:title" value="' + esc(node.title || '') + '" placeholder="Необязательно"></label>'
            + '<label class="builder__setting">CSS-класс'
            + '<input type="text" data-setting="node:class" value="' + esc(node['class'] || '') + '" placeholder="my-hero"></label>'
            + '<label class="builder__setting">Выравнивание по главной оси'
            + '<select data-setting="node:justify">' + options(JUSTIFY, s.justify || '') + '</select></label>'
            + '<label class="builder__setting">Выравнивание по поперечной оси'
            + '<select data-setting="node:align">' + options(ALIGN, s.align || '') + '</select></label>';
    }

    function nodeSettingsHtml(kind, node, path) {
        var s = node.settings || (node.settings = {});
        var html = commonSettingsHtml(node);

        if (kind === 'section') {
            var role = sectionRole(node);
            var globalName = String(s.global || '');
            if (globalName) {
                html += '<div class="builder__global-note">'
                    + icon('global') + ' Глобальная секция <code>' + esc(globalName) + '</code>'
                    + '<p class="muted">Правки здесь видны на всех страницах, где она используется.</p>'
                    + '</div>';
            }
            html += '<label class="builder__setting">Роль'
                + '<select data-setting="node:role">'
                + option('', 'Обычная секция', role)
                + option('header', 'Шапка сайта', role)
                + option('footer', 'Подвал сайта', role)
                + '</select></label>';
            html += '<label class="builder__setting">Ширина'
                + '<select data-setting="node:width">'
                + option('boxed', 'По контейнеру', s.width || 'boxed')
                + option('full', 'На всю ширину', s.width || 'boxed')
                + '</select></label>';
            html += '<label class="builder__setting">Вариант'
                + '<select data-setting="node:variant">'
                + option('', 'Обычный', s.variant || '')
                + option('muted', 'Серый фон', s.variant || '')
                + option('dark', 'Тёмный', s.variant || '')
                + option('accent', 'Акцентный', s.variant || '')
                + '</select></label>';
            html += '<label class="builder__setting">Фон (CSS)'
                + '<input type="text" data-setting="node:background" value="' + esc(s.background || '') + '" placeholder="#f5f5f5 или var(--accent)"></label>';
            html += '<label class="builder__setting">Отступы (CSS)'
                + '<input type="text" data-setting="node:padding" value="' + esc(s.padding || '') + '" placeholder="3rem 1rem"></label>';
        }

        if (kind === 'column') {
            html += '<label class="builder__setting">Ширина (из ' + grid + ')'
                + '<select data-setting="node:width">'
                + option('0', 'Авто', node.width || 0);
            for (var w = 1; w <= grid; w++) {
                html += option(String(w), String(w), node.width || 0);
            }
            html += '</select></label>';
        }

        html += '<div class="builder__settings-actions">';
        if (kind === 'section') {
            html += '<button type="button" class="btn btn-sm btn-outline-danger" data-section-del="' + path[0] + '">'
                + icon('delete') + ' Удалить секцию</button>';
        } else if (kind === 'row') {
            html += '<button type="button" class="btn btn-sm btn-outline-danger" data-row-del="' + path[0] + ':' + path[1] + '">'
                + icon('delete') + ' Удалить ряд</button>';
        } else if (kind === 'column') {
            html += '<button type="button" class="btn btn-sm btn-outline-danger" data-col-del="' + path.join(':') + '">'
                + icon('delete') + ' Удалить колонку</button>';
        }
        html += '</div>';

        return html;
    }

    function blockSettingsHtml(block, path) {
        var type = types[block.type];
        var settings = block.settings || (block.settings = {});
        var html = commonSettingsHtml(block);

        if (type && type.fields) {
            Object.keys(type.fields).forEach(function (key) {
                html += fieldHtml(key, type.fields[key], settings);
            });
        } else {
            html += '<p class="muted">У этого элемента нет настроек.</p>';
        }

        var id = path.join(':');
        html += '<div class="builder__settings-actions">'
            + '<button type="button" class="btn btn-sm btn-outline-danger" data-block-del="' + id + '">'
            + icon('delete') + ' Удалить</button>'
            + '</div>';

        return html;
    }

    function fieldHtml(key, field, settings) {
        var label = field.label || key;
        var value = Object.prototype.hasOwnProperty.call(settings, key)
            ? settings[key]
            : (field['default'] !== undefined ? field['default'] : '');
        var html = '<label class="builder__setting">' + esc(label);

        if (key === 'content_type') {
            html += '<select data-setting="block:' + esc(key) + '">';
            Object.keys(config.menu || {}).forEach(function (name) {
                html += option(name, (config.menu[name].label || name), value);
            });

            return html + '</select></label>';
        }

        if (key === 'filters') {
            var chosenType = settings.content_type || 'page';
            var props = (config.menu && config.menu[chosenType] && config.menu[chosenType].properties) || [];

            return html + '</label>' + filtersHtml(settings[key] || [], props);
        }

        if (key === 'element_type') {
            html += '<select data-setting="block:' + esc(key) + '">';
            html += option('', '— выберите тип —', value);
            Object.keys(config.elements || {}).forEach(function (name) {
                html += option(name, (config.elements[name].label || name), value);
            });

            return html + '</select></label>';
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

            return html + '</label>';
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

            return html + '</label>';
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

        return html + '</label>';
    }

    function filtersHtml(filters, props) {
        var html = '<div class="builder__filters">';
        html += '<div class="builder__filters-head">Фильтры по свойствам</div>';
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

    function option(value, label, current) {
        return '<option value="' + esc(value) + '"' + (String(current) === String(value) ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function options(pairs, current) {
        return pairs.map(function (pair) { return option(pair[0], pair[1], current); }).join('');
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

    function presetColumns(widths, presetBlocks) {
        return widths.map(function (w, ci) {
            var blocks = [];
            (presetBlocks[ci] || []).forEach(function (entry) {
                var block = presetBlock(entry);
                if (block) { blocks.push(block); }
            });

            return { width: w, blocks: blocks };
        });
    }

    function addSection(preset) {
        // Accepts a preset object ({widths, blocks, position}) or a bare widths array.
        var widths = Array.isArray(preset) ? preset : (preset.widths || [grid]);
        var presetBlocks = (!Array.isArray(preset) && Array.isArray(preset.blocks)) ? preset.blocks : [];
        var settings = {};
        if (!Array.isArray(preset)) {
            settings = clone(preset.settings || {});
            if (preset.role === 'header' || preset.role === 'footer') {
                settings.role = preset.role;
            }
        }

        var section = {
            id: uid(),
            settings: settings,
            rows: [{ id: uid(), settings: {}, columns: presetColumns(widths, presetBlocks) }]
        };
        if (!Array.isArray(preset) && preset.position === 'start') {
            sections().unshift(section);
        } else {
            sections().push(section);
        }
        select('section', [sections().indexOf(section)]);
        render();
    }

    /**
     * Insert a reference to a global section. The reference carries only the name
     * and an optional local override; the renderer fills in the shared content.
     */
    function addGlobalSection(name) {
        var reference = { id: uid(), settings: { global: name }, rows: [] };
        var entry = globalCatalog('section')[name];
        var role = entry && entry.role ? entry.role : '';
        if (role === 'footer') {
            sections().push(reference);
        } else {
            sections().unshift(reference);
        }
        select('section', [sections().indexOf(reference)]);
        render();
    }

    function addRow(si) {
        var section = sectionAt(si);
        if (!section) { return; }
        rowsOf(section).push({ id: uid(), settings: {}, columns: [{ width: 0, blocks: [] }] });
        select('row', [si, section.rows.length - 1]);
        render();
    }

    function addColumn(si, ri) {
        var row = rowAt(si, ri);
        if (!row) { return; }
        columnsOf(row).push({ width: 0, blocks: [] });
        select('column', [si, ri, row.columns.length - 1]);
        render();
    }

    function addBlock(typeName, si, ri, ci, index) {
        var column = columnAt(si, ri, ci);
        if (!column) { return; }
        var block = { type: typeName, id: uid(), settings: defaultsFor(typeName) };
        if (index === undefined || index === null || index > column.blocks.length) {
            blocksOf(column).push(block);
        } else {
            column.blocks.splice(index, 0, block);
        }
        select('block', [si, ri, ci, column.blocks.indexOf(block)]);
        render();
    }

    function removeBlock(si, ri, ci, bi) {
        var column = columnAt(si, ri, ci);
        if (column && column.blocks) {
            column.blocks.splice(bi, 1);
            selection = null;
            render();
        }
    }

    function duplicateBlock(si, ri, ci, bi) {
        var column = columnAt(si, ri, ci);
        if (!column || !column.blocks || !column.blocks[bi]) { return; }
        var copy = clone(column.blocks[bi]);
        copy.id = uid();
        column.blocks.splice(bi + 1, 0, copy);
        select('block', [si, ri, ci, bi + 1]);
        render();
    }

    function moveBlock(from, to) {
        var fromColumn = columnAt(from.si, from.ri, from.ci);
        var toColumn = columnAt(to.si, to.ri, to.ci);
        if (!fromColumn || !toColumn) { return; }
        var block = fromColumn.blocks.splice(from.bi, 1)[0];
        var index = to.bi === null || to.bi === undefined ? toColumn.blocks.length : to.bi;
        toColumn.blocks.splice(index, 0, block);
        render();
    }

    function moveSection(from, to) {
        var arr = sections();
        var moved = arr.splice(from, 1)[0];
        arr.splice(to, 0, moved);
        selection = null;
        render();
    }

    function moveRow(si, from, to) {
        var rows = rowsOf(sectionAt(si));
        if (!rows[from]) { return; }
        var index = to > from ? to - 1 : to;
        var moved = rows.splice(from, 1)[0];
        rows.splice(Math.max(0, Math.min(rows.length, index)), 0, moved);
        selection = null;
        render();
    }

    function moveColumn(si, fromRi, fromCi, toRi, toCi) {
        var fromRow = rowAt(si, fromRi);
        var toRow = rowAt(si, toRi);
        if (!fromRow || !toRow || !fromRow.columns[fromCi]) { return; }
        var column = fromRow.columns.splice(fromCi, 1)[0];
        var index = toCi === null || toCi === undefined ? toRow.columns.length : toCi;
        toRow.columns.splice(index, 0, column);
        selection = null;
        render();
    }

    /**
     * Resize a column by one grid step. An "auto" column (width 0) starts from
     * half a row, which is the sensible first width for a fresh column.
     */
    function resizeColumn(si, ri, ci, delta) {
        var column = columnAt(si, ri, ci);
        if (!column) { return; }
        var current = column.width > 0 ? column.width : Math.max(1, Math.floor(grid / 2));
        column.width = Math.max(1, Math.min(grid, current + delta));
        render();
    }

    /**
     * Keep the canvas label in step while the author types a title. A full
     * re-render would drop focus from the input, so only the text changes.
     */
    function refreshLabel(kind, path) {
        var node = nodeAt(path);
        if (!node) { return; }
        var el = null;
        if (kind === 'section') {
            el = canvas.querySelector('.builder__section[data-si="' + path[0] + '"] .builder__section-title');
            if (el) { el.textContent = sectionTitle(node, path[0]); }
        } else if (kind === 'row') {
            el = canvas.querySelector('.builder__row[data-si="' + path[0] + '"][data-ri="' + path[1] + '"] .builder__row-title');
            if (el) { el.textContent = node.title || 'Ряд ' + (path[1] + 1); }
        } else if (kind === 'column') {
            el = canvas.querySelector('.builder__column[data-si="' + path[0] + '"][data-ri="' + path[1] + '"][data-ci="' + path[2] + '"] .builder__column-title');
            if (el) { el.textContent = node.title || (node.width > 0 ? node.width + '/' + grid : 'авто'); }
        } else if (kind === 'block') {
            el = canvas.querySelector('.builder__block.is-selected .builder__block-title');
            if (el) { el.textContent = blockLabel(node); }
        }
    }

    (globalsEl || libraryEl).addEventListener('dragstart', onLibraryDragStart);
    libraryEl.addEventListener('dragstart', onLibraryDragStart);

    function onLibraryDragStart(event) {
        var button = event.target.closest('.builder__particle');
        if (!button) { return; }
        if (button.hasAttribute('data-global-block')) {
            drag = { kind: 'global-block', name: button.getAttribute('data-global-block') };
        } else {
            drag = { kind: 'new', type: button.getAttribute('data-type') };
        }
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', 'builder-particle');
    }

    canvas.addEventListener('dragstart', function (event) {
        var handle = event.target.closest('[data-drag]');
        if (!handle) { return; }
        var kind = handle.getAttribute('data-drag');
        var si = parseInt(handle.getAttribute('data-si'), 10);
        if (kind === 'section') {
            drag = { kind: 'section', si: si };
        } else if (kind === 'row') {
            drag = { kind: 'row', si: si, ri: parseInt(handle.getAttribute('data-ri'), 10) };
        } else if (kind === 'column') {
            drag = {
                kind: 'column',
                si: si,
                ri: parseInt(handle.getAttribute('data-ri'), 10),
                ci: parseInt(handle.getAttribute('data-ci'), 10)
            };
        } else if (kind === 'block') {
            drag = {
                kind: 'block',
                si: si,
                ri: parseInt(handle.getAttribute('data-ri'), 10),
                ci: parseInt(handle.getAttribute('data-ci'), 10),
                bi: parseInt(handle.getAttribute('data-bi'), 10)
            };
        } else {
            return;
        }
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', kind);
    });

    canvas.addEventListener('dragover', function (event) {
        if (!drag) { return; }

        var drop = event.target.closest('[data-drop="blocks"]');
        if (drop && (drag.kind === 'new' || drag.kind === 'global-block' || drag.kind === 'block')) {
            event.preventDefault();
            drop.classList.add('is-over');
            highlightDropTarget(drop, event);
        }

        var columns = event.target.closest('[data-drop="columns"]');
        if (columns && drag.kind === 'column') {
            event.preventDefault();
            columns.classList.add('is-over');
            highlightColumnTarget(columns, event);
        }

        var section = event.target.closest('.builder__section');
        if (drag.kind === 'section' && section) {
            event.preventDefault();
            section.classList.add('is-dragover');
        }

        var row = event.target.closest('.builder__row');
        if (drag.kind === 'row' && row) {
            event.preventDefault();
            row.classList.add('is-dragover');
        }
    });

    canvas.addEventListener('dragleave', function (event) {
        var drop = event.target.closest('[data-drop="blocks"], [data-drop="columns"]');
        if (drop) { drop.classList.remove('is-over'); }
        var section = event.target.closest('.builder__section');
        if (section) { section.classList.remove('is-dragover'); }
        var row = event.target.closest('.builder__row');
        if (row) { row.classList.remove('is-dragover'); }
    });

    canvas.addEventListener('drop', function (event) {
        if (!drag) { return; }

        var drop = event.target.closest('[data-drop="blocks"]');
        if (drop && (drag.kind === 'new' || drag.kind === 'global-block' || drag.kind === 'block')) {
            event.preventDefault();
            drop.classList.remove('is-over');
            var si = parseInt(drop.getAttribute('data-si'), 10);
            var ri = parseInt(drop.getAttribute('data-ri'), 10);
            var ci = parseInt(drop.getAttribute('data-ci'), 10);
            var index = dropIndex(drop, event);

            if (drag.kind === 'new') {
                addBlock(drag.type, si, ri, ci, index);
            } else if (drag.kind === 'global-block') {
                addBlock('global', si, ri, ci, index);
                var column = columnAt(si, ri, ci);
                var at = index === null ? column.blocks.length - 1 : index;
                var added = column.blocks[at];
                if (added) {
                    added.settings = added.settings || {};
                    added.settings.name = drag.name;
                }
                render();
            } else {
                var sameColumn = drag.si === si && drag.ri === ri && drag.ci === ci;
                if (!sameColumn || (index !== null && index !== drag.bi && index !== drag.bi + 1)) {
                    moveBlock(
                        { si: drag.si, ri: drag.ri, ci: drag.ci, bi: drag.bi },
                        { si: si, ri: ri, ci: ci, bi: index }
                    );
                }
            }
            drag = null;

            return;
        }

        var columns = event.target.closest('[data-drop="columns"]');
        if (columns && drag.kind === 'column') {
            event.preventDefault();
            columns.classList.remove('is-over');
            var tsi = parseInt(columns.getAttribute('data-si'), 10);
            var tri = parseInt(columns.getAttribute('data-ri'), 10);
            var tci = columnIndex(columns, event);
            var same = drag.si === tsi && drag.ri === tri;
            if (!same || (tci !== null && tci !== drag.ci && tci !== drag.ci + 1)) {
                moveColumn(drag.si, drag.ri, drag.ci, tsi, tri, tci);
            }
            drag = null;

            return;
        }

        var section = event.target.closest('.builder__section');
        if (drag.kind === 'section' && section) {
            event.preventDefault();
            var to = parseInt(section.getAttribute('data-si'), 10);
            section.classList.remove('is-dragover');
            if (to !== drag.si) { moveSection(drag.si, to); }
            drag = null;

            return;
        }

        var row = event.target.closest('.builder__row');
        if (drag.kind === 'row' && row) {
            event.preventDefault();
            row.classList.remove('is-dragover');
            var rsi = parseInt(row.getAttribute('data-si'), 10);
            var rri = parseInt(row.getAttribute('data-ri'), 10);
            if (rsi === drag.si && rri !== drag.ri) {
                moveRow(rsi, drag.ri, rri);
            }
            drag = null;
        }
    });

    function highlightDropTarget(drop, event) {
        var cards = Array.prototype.slice.call(drop.querySelectorAll('.builder__block'));
        cards.forEach(function (card) { card.classList.remove('is-before', 'is-after'); });
        var card = event.target.closest('.builder__block');
        if (!card) { return; }
        var rect = card.getBoundingClientRect();
        card.classList.add(event.clientY > rect.top + rect.height / 2 ? 'is-after' : 'is-before');
    }

    function dropIndex(drop, event) {
        var card = event.target.closest('.builder__block');
        if (!card) { return null; }
        var bi = parseInt(card.getAttribute('data-bi'), 10);
        var rect = card.getBoundingClientRect();

        return event.clientY > rect.top + rect.height / 2 ? bi + 1 : bi;
    }

    function highlightColumnTarget(container, event) {
        var columns = Array.prototype.slice.call(container.querySelectorAll('.builder__column'));
        columns.forEach(function (column) { column.classList.remove('is-before', 'is-after'); });
        var column = event.target.closest('.builder__column');
        if (!column) { return; }
        var rect = column.getBoundingClientRect();
        column.classList.add(event.clientX > rect.left + rect.width / 2 ? 'is-after' : 'is-before');
    }

    function columnIndex(container, event) {
        var column = event.target.closest('.builder__column');
        if (!column) { return null; }
        var ci = parseInt(column.getAttribute('data-ci'), 10);
        var rect = column.getBoundingClientRect();

        return event.clientX > rect.left + rect.width / 2 ? ci + 1 : ci;
    }

    canvas.addEventListener('click', onEditorClick);
    // The settings panel repeats delete/duplicate actions, so share the handler.
    settingsBody.addEventListener('click', onEditorClick);

    function pathOf(el, attrs) {
        var path = [];
        attrs.forEach(function (attr) {
            path.push(parseInt(el.getAttribute(attr), 10));
        });

        return path;
    }

    function onEditorClick(event) {
        // Shared markup belongs to a global, so a click in it opens that global
        // instead of selecting a node of this page (whose indices do not apply).
        var shared = event.target.closest('[data-global-shared]');
        if (shared && !event.target.closest('a')) {
            window.location.href = '/admin/builder?global=section:' + encodeURIComponent(shared.getAttribute('data-global-shared'));

            return;
        }

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
            promote('section', [parseInt(promoteSection.getAttribute('data-promote-section'), 10)]);

            return;
        }

        var promoteBlock = event.target.closest('[data-promote-block]');
        if (promoteBlock) {
            promote('block', promoteBlock.getAttribute('data-promote-block').split(':').map(Number));

            return;
        }

        var del = event.target.closest('[data-block-del]');
        if (del) {
            var p = del.getAttribute('data-block-del').split(':').map(Number);
            removeBlock(p[0], p[1], p[2], p[3]);

            return;
        }

        var dup = event.target.closest('[data-block-dup]');
        if (dup) {
            var pd = dup.getAttribute('data-block-dup').split(':').map(Number);
            duplicateBlock(pd[0], pd[1], pd[2], pd[3]);

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

        var rowAdd = event.target.closest('[data-row-add]');
        if (rowAdd) {
            addRow(parseInt(rowAdd.getAttribute('data-row-add'), 10));

            return;
        }

        var rowDel = event.target.closest('[data-row-del]');
        if (rowDel) {
            var pr = rowDel.getAttribute('data-row-del').split(':').map(Number);
            var section = sectionAt(pr[0]);
            if (section && section.rows) {
                section.rows.splice(pr[1], 1);
                selection = null;
                render();
            }

            return;
        }

        var colDel = event.target.closest('[data-col-del]');
        if (colDel) {
            var pc = colDel.getAttribute('data-col-del').split(':').map(Number);
            var row = rowAt(pc[0], pc[1]);
            if (row && row.columns) {
                row.columns.splice(pc[2], 1);
                selection = null;
                render();
            }

            return;
        }

        var colAdd = event.target.closest('[data-col-add]');
        if (colAdd) {
            var pa = colAdd.getAttribute('data-col-add').split(':').map(Number);
            addColumn(pa[0], pa[1]);

            return;
        }

        var wider = event.target.closest('[data-col-width-up]');
        if (wider) {
            var pu = wider.getAttribute('data-col-width-up').split(':').map(Number);
            resizeColumn(pu[0], pu[1], pu[2], 1);

            return;
        }

        var narrower = event.target.closest('[data-col-width-down]');
        if (narrower) {
            var pn = narrower.getAttribute('data-col-width-down').split(':').map(Number);
            resizeColumn(pn[0], pn[1], pn[2], -1);

            return;
        }

        if (event.target.closest('.builder__icon-btn')) { return; }

        var card = event.target.closest('.builder__block');
        if (card) {
            select('block', pathOf(card, ['data-si', 'data-ri', 'data-ci', 'data-bi']));
            render();

            return;
        }

        var columnEl = event.target.closest('.builder__column');
        if (columnEl) {
            select('column', pathOf(columnEl, ['data-si', 'data-ri', 'data-ci']));
            render();

            return;
        }

        var rowEl = event.target.closest('.builder__row');
        if (rowEl) {
            select('row', pathOf(rowEl, ['data-si', 'data-ri']));
            render();

            return;
        }

        var sectionEl = event.target.closest('.builder__section');
        if (sectionEl) {
            select('section', pathOf(sectionEl, ['data-si']));
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
                filters.forEach(function (filter) { filter.field = ''; });
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
        if (!pick || !selection || selection.kind !== 'block' || pick.value === '') { return; }
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
        var block = blockAt(selection.path[0], selection.path[1], selection.path[2], selection.path[3]);
        if (!block) { return null; }
        block.settings = block.settings || {};
        if (!Array.isArray(block.settings.filters)) {
            block.settings.filters = [];
        }

        return block.settings.filters;
    }

    function setBlockSetting(key, value) {
        if (!selection || selection.kind !== 'block') { return; }
        var block = blockAt(selection.path[0], selection.path[1], selection.path[2], selection.path[3]);
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
        var raw = el.getAttribute('data-filter-field');
        if (raw === null) { raw = el.getAttribute('data-filter-op'); }
        if (raw === null) { raw = el.getAttribute('data-filter-value'); }
        var index = parseInt(raw, 10);
        if (!filters[index]) { return; }
        if (el.hasAttribute('data-filter-field')) { filters[index].field = el.value; }
        else if (el.hasAttribute('data-filter-op')) { filters[index].op = el.value; }
        else { filters[index].value = el.value; }
        syncJson();
    }

    /**
     * One handler for every level: "node:title" and "node:class" are the node's
     * own fields, the rest live in its settings, and "block:key" reaches into a
     * particle's settings.
     */
    function onSettingChange(event) {
        var el = event.target.closest('[data-setting]');
        if (!el || !selection) { return; }
        var parts = el.getAttribute('data-setting').split(':');
        var scope = parts[0];
        var key = parts[1];
        var value = el.type === 'checkbox' ? !!el.checked : el.value;

        if (scope === 'block') {
            var block = blockAt(selection.path[0], selection.path[1], selection.path[2], selection.path[3]);
            if (block) {
                block.settings = block.settings || {};
                block.settings[key] = value;
            }
            syncJson();
            updateCardSummary();

            return;
        }

        if (scope !== 'node') { return; }
        var node = nodeAt(selection.path);
        if (!node) { return; }

        if (key === 'title' || key === 'class') {
            if (value === '') {
                delete node[key];
            } else {
                node[key] = value;
            }
            syncJson();
            refreshLabel(selection.kind, selection.path);

            return;
        }

        if (key === 'width' && selection.kind === 'column') {
            node.width = parseInt(value, 10) || 0;
            syncJson();
            renderCanvas();

            return;
        }

        node.settings = node.settings || {};
        node.settings[key] = value;
        syncJson();
        renderCanvas();
    }

    function updateCardSummary() {
        if (!selection || selection.kind !== 'block') { return; }
        var card = canvas.querySelector('.builder__block.is-selected .builder__block-summary');
        var block = blockAt(selection.path[0], selection.path[1], selection.path[2], selection.path[3]);
        if (card && block) {
            card.textContent = summaryFor(block);
        }
    }

    presetsEl.addEventListener('click', function (event) {
        // Global sections are inserted from this menu, because a section is a
        // top-level band and cannot be dropped into a column.
        var globalSection = event.target.closest('[data-global-section-add]');
        if (globalSection) {
            addGlobalSection(globalSection.getAttribute('data-global-section-add'));

            return;
        }

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
            var blockButton = event.target.closest('[data-global-block]');
            if (!blockButton) { return; }

            // Add to the first column of the last body section, or start one.
            var target = null;
            displayOrder().forEach(function (entry) {
                if (!sectionRole(entry.section)) { target = entry; }
            });
            if (!target) {
                addSection([grid]);
                target = { si: sections().length - 1 };
            }
            var section = sectionAt(target.si);
            if (!section) { return; }
            if (!rowsOf(section).length) {
                rowsOf(section).push({ id: uid(), settings: {}, columns: [{ width: 0, blocks: [] }] });
            }
            var row = section.rows[0];
            if (!columnsOf(row).length) {
                row.columns.push({ width: 0, blocks: [] });
            }
            addBlock('global', target.si, 0, 0, (row.columns[0].blocks || []).length);
            var added = columnAt(target.si, 0, 0).blocks.slice(-1)[0];
            if (added) {
                added.settings = added.settings || {};
                added.settings.name = blockButton.getAttribute('data-global-block');
            }
            render();
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
