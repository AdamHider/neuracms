/*
 * Minimal block editor: renders the block list from the JSON textarea and keeps
 * the two in sync. No framework, so it degrades to plain JSON editing without JS.
 */
(function () {
    'use strict';

    var form = document.getElementById('builder-form');
    if (!form) {
        return;
    }

    var container = document.getElementById('builder-blocks');
    var textarea = document.getElementById('builder-json');
    var addButton = document.getElementById('builder-add');
    var addType = document.getElementById('builder-add-type');
    var root = form.querySelector('.builder');
    var types = {};

    try {
        (JSON.parse(root.getAttribute('data-types')) || []).forEach(function (type) {
            types[type.name] = type;
        });
    } catch (e) {
        types = {};
    }

    function read() {
        try {
            var data = JSON.parse(textarea.value);
            if (Array.isArray(data)) {
                return { blocks: data };
            }
            if (!data.blocks) {
                data.blocks = [];
            }
            return data;
        } catch (e) {
            return { blocks: [] };
        }
    }

    function write(data) {
        textarea.value = JSON.stringify(data, null, 2);
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

    function label(type) {
        return (types[type] && types[type].title) || type;
    }

    function render() {
        var data = read();
        container.innerHTML = '';

        data.blocks.forEach(function (block, index) {
            var row = document.createElement('div');
            row.className = 'builder__block';

            var head = document.createElement('div');
            head.className = 'builder__block-head';

            var title = document.createElement('strong');
            title.textContent = label(block.type);
            head.appendChild(title);

            var actions = document.createElement('span');
            actions.className = 'builder__block-actions';

            var up = document.createElement('button');
            up.type = 'button';
            up.textContent = '↑';
            up.disabled = index === 0;
            up.addEventListener('click', function () {
                var moved = data.blocks.splice(index, 1)[0];
                data.blocks.splice(index - 1, 0, moved);
                write(data);
                render();
            });

            var down = document.createElement('button');
            down.type = 'button';
            down.textContent = '↓';
            down.disabled = index === data.blocks.length - 1;
            down.addEventListener('click', function () {
                var moved = data.blocks.splice(index, 1)[0];
                data.blocks.splice(index + 1, 0, moved);
                write(data);
                render();
            });

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'danger';
            remove.textContent = 'Удалить';
            remove.addEventListener('click', function () {
                data.blocks.splice(index, 1);
                write(data);
                render();
            });

            actions.appendChild(up);
            actions.appendChild(down);
            actions.appendChild(remove);
            head.appendChild(actions);
            row.appendChild(head);

            var type = types[block.type];
            if (type && type.fields) {
                Object.keys(type.fields).forEach(function (key) {
                    var field = type.fields[key];
                    var wrap = document.createElement('label');
                    wrap.className = 'builder__field';
                    wrap.appendChild(document.createTextNode(field.label || key));

                    var input;
                    if (field.type === 'select' && Array.isArray(field.options)) {
                        input = document.createElement('select');
                        field.options.forEach(function (option) {
                            var opt = document.createElement('option');
                            opt.value = option;
                            opt.textContent = option;
                            input.appendChild(opt);
                        });
                    } else {
                        input = document.createElement('input');
                        input.type = field.type === 'int' ? 'number' : 'text';
                    }

                    var current = block.settings && Object.prototype.hasOwnProperty.call(block.settings, key)
                        ? block.settings[key]
                        : field['default'];
                    input.value = current === undefined || current === null ? '' : current;
                    input.addEventListener('input', function () {
                        block.settings = block.settings || {};
                        block.settings[key] = input.value;
                        write(data);
                    });
                    input.addEventListener('change', function () {
                        block.settings = block.settings || {};
                        block.settings[key] = input.value;
                        write(data);
                    });

                    wrap.appendChild(input);
                    row.appendChild(wrap);
                });
            }

            container.appendChild(row);
        });
    }

    addButton.addEventListener('click', function () {
        var name = addType.value;
        var data = read();
        data.blocks.push({ type: name, settings: defaultsFor(name) });
        write(data);
        render();
    });

    textarea.addEventListener('change', render);

    render();
})();
