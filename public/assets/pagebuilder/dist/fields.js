function createField(key, value, property){
    var input = null
    if(value.type == 'text.input'){
        input = $('<input>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            value: property || value.default
        })
    } else if(value.type == 'number.input'){
        input = $('<input>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
            value: property || value.default
        })
    } else if (value.type == 'text.textarea'){
        input =  $('<textarea>').attr({
            code: value.code,
            id: `component_${key}`,
            class: 'form-control',
        }).html(property || value.default)
    } 

    return $('<div class="mb-3">').append(
        $('<label>').attr('for', key).addClass('form-label').text(value.label),
        $(input)
    );
}