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

    var config = { layout: { sections: [] }, types: [], categories: {}, presets: [], columns: 12 };
    try {
        config = JSON.parse(root.getAttribute('data-builder')) || config;
    } catch (e) {
        config = config;
    }

    var types = {};
    (config.types || []).forEach(function (t) { types[t.name] = t; });

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
        return '<i class="bi bi-' + esc(name || 'square') + '" aria-hidden="true"></i>';
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

    function blockLabel(block) {
        return (types[block.type] && types[block.type].title) || block.type;
    }

    function blockIcon(block) {
        return (types[block.type] && types[block.type].icon) || 'square';
    }

    function render() {
        renderLibrary();
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

    function renderCanvas() {
        if (!sections().length) {
            canvas.innerHTML = '<div class="builder__empty">'
                + '<p>Макет пуст.</p>'
                + '<p class="muted">Перетащите элемент из библиотеки слева или добавьте секцию.</p>'
                + '</div>';
            return;
        }

        var html = '';
        sections().forEach(function (section, si) {
            var selected = selection && selection.kind === 'section' && selection.path[0] === si;
            html += '<section class="builder__section' + (selected ? ' is-selected' : '') + '" data-si="' + si + '">';
            html += '<header class="builder__section-head">'
                + '<span class="builder__handle" draggable="true" data-drag="section" data-si="' + si + '" title="Перетащить секцию">' + icon('drag') + '</span>'
                + '<span class="builder__section-title">Секция ' + (si + 1) + '</span>'
                + '<span class="builder__section-meta">' + (section.columns || []).length + ' кол.</span>'
                + '<span class="builder-bar__spacer"></span>'
                + '<button type="button" class="builder__icon-btn" data-section-dup="' + si + '" title="Дублировать">' + icon('copy') + '</button>'
                + '<button type="button" class="builder__icon-btn" data-section-del="' + si + '" title="Удалить секцию">' + icon('delete') + '</button>'
                + '</header>';

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
        var html = '<div class="builder__settings-title">' + icon('section') + ' Секция ' + (si + 1) + '</div>';

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
            + '</div>';

        settingsBody.innerHTML = html;
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

        settingsBody.innerHTML = html;
    }

    function option(value, label, current) {
        return '<option value="' + esc(value) + '"' + (String(current) === String(value) ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function addSection(widths) {
        var columns = widths.map(function (w) {
            return { width: w, blocks: [] };
        });
        sections().push({ id: uid(), settings: {}, columns: columns });
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

    libraryEl.addEventListener('dragstart', function (event) {
        var button = event.target.closest('.builder__particle');
        if (!button) { return; }
        drag = { kind: 'new', type: button.getAttribute('data-type') };
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', drag.type);
    });

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

    canvas.addEventListener('click', function (event) {
        var del = event.target.closest('[data-block-del]');
        if (del) {
            var p = del.getAttribute('data-block-del').split(':').map(Number);
            removeBlock(p[0], p[1], p[2]);
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
    });

    settingsBody.addEventListener('input', onSettingChange);
    settingsBody.addEventListener('change', onSettingChange);

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
