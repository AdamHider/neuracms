function createField(key, value, property) {
    var input = null;
    if (value.type == 'text.input') {
        input = $('<input>').attr({
            type: 'text',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control form-control-sm',
            value: property || value.default,
            'data-key': key
        });
    } else if (value.type == 'number.input') {
        input = $('<input>').attr({
            type: 'number',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control form-control-sm',
            value: property || value.default,
            'data-key': key
        });
        $(input).attr('data-key', key);
    } else if (value.type == 'text.textarea') {
        input = $('<textarea>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-control form-control-sm',
            'data-key': key
        }).html(property || value.default);
        $(input).attr('data-key', key);
    } else if (value.type == 'checkbox') {
        input = $('<input>').attr({
            type: 'checkbox',
            code: value.code,
            id: `component_${key}`,
            class: 'form-check-input form-control-sm',
            checked: property || value.default,
            'data-key': key
        });
        $(input).attr('data-key', key);
    } else if (value.type == 'select') {
        input = $('<select>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-select form-control-sm',
            'data-key': key
        });
        $(input).attr('data-key', key);
        value.options.forEach(option => {
            const optionElement = $('<option>').attr('value', option.value).text(option.label);
            if (option.value == property) {
                optionElement.attr('selected', 'selected');
            }
            if (option.value == value.default) {
                optionElement.attr('selected', 'selected');
            }
            input.append(optionElement);
        });
    } else if (value.type == 'color.picker') {
        input = $('<input>').attr({
            type: 'text',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control form-control-sm',
            value: property || value.default,
            'data-key': key
        })
        input.colorPicker({
            renderCallback: function($elm, toggled) {
                $($elm).val($elm._css.backgroundColor).trigger("input");
            }
        })
    } else if (value.type == 'image.picker') {
        input = $('<input>').attr({
            type: 'text',
            code: value.code,
            id: `component_${key}`,
            class: 'form-control form-control-sm',
            value: property || value.default,
            'data-key': key
        })
        input.on('click', () => {
            let modal = new bootstrap.Modal(document.getElementById('pickerModal'), {})
            initFileExplorer({
                filePickerElement: '#file_picker',
                multipleMode: false,
                pickerMode: true,
                onPicked: (url) => {
                    $(input).val('/image'+url).trigger("input");
                    modal.hide()
                }
            });
            
            modal.show()
        })
        
  

    }

    
    
    return $(`<div class="col ${value?.class ?? 'col-12'}">`).append(
        $('<label>').attr('for', key).addClass('form-label').text(value.label),
        $(input)
    );
}

