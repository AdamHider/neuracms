function createField(key, value, property) {
    var input = null;
    if (value.type == 'text.input') {
        input = $('<input>').attr({
            type: 'text',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            value: property || value.default,
            'data-key': key
        });
    } else if (value.type == 'number.input') {
        input = $('<input>').attr({
            type: 'number',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            value: property || value.default,
            'data-key': key
        });
        $(input).attr('data-key', key);
    } else if (value.type == 'text.textarea') {
        input = $('<textarea>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            'data-key': key
        }).html(property || value.default);
        $(input).attr('data-key', key);
    } else if (value.type == 'checkbox') {
        input = $('<input>').attr({
            type: 'checkbox',
            code: value.code,
            id: `component_${key}`,
            class: 'form-check-input',
            checked: property || value.default,
            'data-key': key
        });
        $(input).attr('data-key', key);
    } else if (value.type == 'select') {
        input = $('<select>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-select',
            'data-key': key
        });
        $(input).attr('data-key', key);
        value.options.forEach(option => {
            const optionElement = $('<option>').attr('value', option.value).text(option.label);
            if (option.value == property) {
                optionElement.attr('selected', 'selected');
            }
            input.append(optionElement);
        });
    } else if (value.type == 'color.picker') {
        input = $('<input>').attr({
            type: 'text',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            value: property || value.default,
            'data-key': key
        })
        input.colorPicker({
            renderCallback: function($elm, toggled) {
                $($elm).val($elm._css.backgroundColor).trigger("input");
            }
        })
    }

    return $('<div class="mb-3">').append(
        $('<label>').attr('for', key).addClass('form-label').text(value.label),
        $(input)
    );
}

function createColorPicker(key, value, property) {
    const container = $('<div class="color-picker-container"></div>');

    const presetColors = value.presetColors || [];
    const presetContainer = $('<div class="preset-colors d-flex mb-2"></div>');
    presetColors.forEach(color => {
        const colorSwatch = $('<div class="color-swatch rounded-circle me-2"></div>').css({
            'background-color': color,
            'width': '30px',
            'height': '30px',
            'cursor': 'pointer'
        }).attr('data-color', color);
        if (color === property) {
            colorSwatch.addClass('border border-2 border-dark');
        }
        presetContainer.append(colorSwatch);
    });
    container.append(presetContainer);

    const customColorInput = $('<input class="form-control form-control-color">').val(property || value.default).attr('data-key', key).colorpicker();
    container.append(customColorInput);

    container.on('click', '.color-swatch', function() {
        container.find('.color-swatch').removeClass('border border-2 border-dark');
        $(this).addClass('border border-2 border-dark');
        customColorInput.val($(this).data('color')).trigger('input');
    });

    customColorInput.on('input', function() {
        container.find('.color-swatch').removeClass('border border-2 border-dark');
    });

    return container;
}
